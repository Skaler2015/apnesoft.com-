<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Http;

/**
 * RSS / Atom feed ingestion. Feeds are treated as update signals: each item is
 * matched to existing software by title/link; genuinely new items may create
 * review-pending records.
 */
final class RssService
{
    public static function sync(array $source): array
    {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $url = $source['source_url'] ?? '';
        if ($url === '') {
            return $stats;
        }

        $resp = Http::get($url, ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml']);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            $stats['failed']++;
            return $stats;
        }

        $items = self::parse($resp['body']);
        $config = json_decode($source['config'] ?? '{}', true) ?: [];

        foreach ($items as $item) {
            $stats['processed']++;
            if (empty($item['title'])) {
                $stats['skipped']++;
                continue;
            }

            $version = self::extractVersion($item['title'] . ' ' . ($item['summary'] ?? ''));

            $dto = [
                'name'             => self::cleanName($item['title']),
                'developer_name'   => $config['developer'] ?? ($source['name'] ?? ''),
                'official_website' => $item['link'] ?? ($source['source_url'] ?? ''),
                'source_url'       => $item['link'] ?? '',
                'short_description' => str_excerpt($item['summary'] ?? '', 300),
                'long_description' => $item['summary'] ?? '',
                'version'          => $version,
                'release_date'     => $item['date'] ?? null,
                'category_id'      => $config['category_id'] ?? Classifier::detectCategory($item['title'], $item['summary'] ?? ''),
                'price_type'       => $config['price_type'] ?? null,
                'source_id'        => (int) $source['id'],
                'source_type'      => 'rss',
            ];

            $result = Ingest::process($dto);
            match ($result['action']) {
                'published', 'review' => $stats['created']++,
                'updated', 'refreshed' => $stats['updated']++,
                default => $stats['skipped']++,
            };
        }

        return $stats;
    }

    /** @return array<int, array{title:string, link:string, summary:string, date:?string}> */
    public static function parse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }
        $out = [];

        // RSS 2.0
        if (isset($doc->channel->item)) {
            foreach ($doc->channel->item as $item) {
                $out[] = [
                    'title'   => trim((string) $item->title),
                    'link'    => trim((string) $item->link),
                    'summary' => trim(strip_tags((string) $item->description)),
                    'date'    => self::toDate((string) $item->pubDate),
                ];
            }
            return $out;
        }

        // Atom
        if (isset($doc->entry)) {
            foreach ($doc->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    if ((string) $l['rel'] === '' || (string) $l['rel'] === 'alternate') {
                        $link = (string) $l['href'];
                        break;
                    }
                }
                $out[] = [
                    'title'   => trim((string) $entry->title),
                    'link'    => $link,
                    'summary' => trim(strip_tags((string) ($entry->summary ?: $entry->content))),
                    'date'    => self::toDate((string) ($entry->updated ?: $entry->published)),
                ];
            }
        }
        return $out;
    }

    private static function extractVersion(string $text): string
    {
        if (preg_match('/\bv?(\d+(?:\.\d+){1,3}(?:[-\w.]+)?)\b/', $text, $m)) {
            return $m[1];
        }
        return '';
    }

    private static function cleanName(string $title): string
    {
        // Strip trailing version + "released" style suffixes.
        $title = preg_replace('/\s+v?\d+(\.\d+){1,3}.*/i', '', $title) ?? $title;
        $title = preg_replace('/\b(released|update|now available|is out).*/i', '', $title) ?? $title;
        return trim($title) ?: $title;
    }

    private static function toDate(string $raw): ?string
    {
        $ts = strtotime($raw);
        return $ts ? gmdate('Y-m-d', $ts) : null;
    }
}
