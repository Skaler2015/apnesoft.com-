<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Http;

/**
 * Controlled official-website crawler. Respects robots.txt, fetches a single
 * page politely, and parses JSON-LD / meta tags / visible version strings.
 * Does NOT bypass anti-bot systems, CAPTCHAs, or authentication.
 */
final class WebsiteCrawler
{
    public static function sync(array $source): array
    {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $url = $source['source_url'] ?? '';
        if ($url === '') {
            return $stats;
        }

        $stats['processed']++;

        if (!self::robotsAllows($url)) {
            $stats['skipped']++;
            return $stats;
        }

        $resp = Http::get($url);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            $stats['failed']++;
            return $stats;
        }

        $html = $resp['body'];
        $meta = self::parseMeta($html);
        $jsonld = self::parseJsonLd($html);
        $config = json_decode($source['config'] ?? '{}', true) ?: [];

        $name = $jsonld['name'] ?? $config['name'] ?? $meta['og:site_name'] ?? $meta['title'] ?? '';
        if ($name === '') {
            $stats['skipped']++;
            return $stats;
        }

        $version = $jsonld['softwareVersion'] ?? self::detectVersion($html);

        $dto = [
            'name'                  => $name,
            'developer_name'        => $jsonld['author']['name'] ?? ($config['developer'] ?? ''),
            'official_website'      => $url,
            'official_download_url' => $config['download_url'] ?? $url,
            'short_description'     => str_excerpt($meta['description'] ?? ($jsonld['description'] ?? ''), 300),
            'long_description'      => $meta['description'] ?? ($jsonld['description'] ?? ''),
            'version'               => $version,
            'license_type'          => $config['license'] ?? null,
            'operating_system'      => $jsonld['operatingSystem'] ?? ($config['os'] ?? ''),
            'price_type'            => $config['price_type'] ?? null,
            'category_id'           => $config['category_id'] ?? Classifier::detectCategory($name, $meta['description'] ?? ''),
            'source_id'             => (int) $source['id'],
            'source_type'           => 'website',
            'source_url'            => $url,
            'external_ref'          => 'web:' . self::host($url),
        ];

        $result = Ingest::process($dto);
        match ($result['action']) {
            'published', 'review' => $stats['created']++,
            'updated', 'refreshed' => $stats['updated']++,
            default => $stats['skipped']++,
        };
        return $stats;
    }

    public static function robotsAllows(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }
        $robots = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . '/robots.txt';
        $resp = Http::get($robots, [], 8);
        if ($resp['status'] !== 200) {
            return true; // no robots.txt -> allowed
        }
        // Very small robots parser: honor a global Disallow: / for our UA/*
        $path = $parts['path'] ?? '/';
        $lines = preg_split('/\r?\n/', $resp['body']) ?: [];
        $applies = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if (stripos($line, 'User-agent:') === 0) {
                $agent = trim(substr($line, 11));
                $applies = ($agent === '*');
            } elseif ($applies && stripos($line, 'Disallow:') === 0) {
                $rule = trim(substr($line, 9));
                if ($rule !== '' && str_starts_with($path, $rule)) {
                    return false;
                }
            }
        }
        return true;
    }

    private static function parseMeta(string $html): array
    {
        $meta = [];
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $meta['title'] = trim(html_entity_decode(strip_tags($m[1])));
        }
        if (preg_match_all('/<meta\s+[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                $name = '';
                if (preg_match('/(?:name|property)\s*=\s*["\']([^"\']+)["\']/i', $tag, $n)) {
                    $name = strtolower($n[1]);
                }
                if ($name !== '' && preg_match('/content\s*=\s*["\']([^"\']*)["\']/i', $tag, $c)) {
                    $meta[$name] = trim(html_entity_decode($c[1]));
                }
            }
        }
        return $meta;
    }

    private static function parseJsonLd(string $html): array
    {
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
            foreach ($m[1] as $block) {
                $data = json_decode(trim($block), true);
                if (!is_array($data)) {
                    continue;
                }
                $nodes = isset($data['@type']) ? [$data] : $data;
                foreach ($nodes as $node) {
                    if (is_array($node) && ($node['@type'] ?? '') === 'SoftwareApplication') {
                        return $node;
                    }
                }
            }
        }
        return [];
    }

    private static function detectVersion(string $html): string
    {
        if (preg_match('/version\s*[:\-]?\s*v?(\d+(?:\.\d+){1,3})/i', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    private static function host(string $url): string
    {
        $h = parse_url($url, PHP_URL_HOST);
        return $h ? preg_replace('/^www\./', '', strtolower($h)) : $url;
    }
}
