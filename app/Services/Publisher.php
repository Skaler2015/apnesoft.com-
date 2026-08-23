<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;
use App\Support\Http;

/**
 * One place that turns a software NAME or official URL into a published record:
 * looks it up on official catalogues (or scrapes a page), skips duplicates,
 * creates the row, optionally enriches it with AI and captures a screenshot.
 * Used by the admin (live bulk publish) and by the background queue (cron).
 */
final class Publisher
{
    /**
     * @return array{status:string, name:string, id?:int, slug?:string}
     *   status = published | duplicate | notfound
     */
    public static function publish(string $name, ?string $url = null, ?string $osSlug = null, bool $withAi = true): array
    {
        $name = trim($name);
        $url = $url !== null ? trim($url) : '';
        $label = $name !== '' ? $name : $url;

        $d = $url !== '' ? self::extractFromUrl($url) : SoftwareLookup::search($name);
        if ($d === null || empty($d['name'])) {
            return ['status' => 'notfound', 'name' => $label];
        }

        Dedupe::ensureSchema();
        $key = Dedupe::key((string) $d['name']);
        if ($key !== '' && Database::scalar('SELECT id FROM software WHERE dedupe_key = :k LIMIT 1', ['k' => $key])) {
            return ['status' => 'duplicate', 'name' => (string) $d['name']];
        }

        $osIds = [];
        if ($osSlug) {
            $osId = (int) (Database::scalar('SELECT id FROM operating_systems WHERE slug = :s', ['s' => $osSlug]) ?: 0);
            if ($osId) {
                $osIds = [$osId];
            }
        }

        $id = self::createFromDto($d, $osIds);

        // Capture a real website screenshot (free WordPress mShots) when enabled.
        if ((string) Settings::get('auto_screenshot', '0') === '1' && !empty($d['official_website'])) {
            $shot = 'https://s.wordpress.com/mshots/v1/' . rawurlencode((string) $d['official_website']) . '?w=1280';
            try {
                Database::run('INSERT INTO software_screenshots (software_id, url, sort_order) VALUES (:s, :u, 2)', ['s' => $id, 'u' => $shot]);
            } catch (\Throwable $e) {}
        }

        if ($withAi && AiEnhancer::isConfigured()) {
            try { AiEnhancer::enhance($id); } catch (\Throwable $e) {}
        }
        Seo::generateForSoftware($id);

        return [
            'status' => 'published',
            'name'   => (string) $d['name'],
            'id'     => $id,
            'slug'   => (string) Database::scalar('SELECT slug FROM software WHERE id = :i', ['i' => $id]),
        ];
    }

    /** Create + publish one software from a lookup DTO; returns the new id. */
    public static function createFromDto(array $d, array $osIds): int
    {
        $osLabel = $osIds ? Classifier::osLabel($osIds) : ($d['operating_system'] ?? null);
        $data = [
            'name'                  => (string) ($d['name'] ?? ''),
            'developer_name'        => $d['developer_name'] ?? null,
            'developer_website'     => $d['developer_website'] ?? null,
            'official_website'      => $d['official_website'] ?? null,
            'official_download_url' => $d['official_download_url'] ?? null,
            'short_description'     => $d['short_description'] ?? null,
            'long_description'      => $d['long_description'] ?? null,
            'version'               => $d['version'] ?? null,
            'license_type'          => $d['license_type'] ?? null,
            'price_type'            => $d['price_type'] ?? null,
            'is_open_source'        => !empty($d['is_open_source']) ? 1 : 0,
            'operating_system'      => $osLabel ?: null,
            'category_id'           => $d['category_id'] ?? null,
            'logo'                  => $d['logo'] ?? null,
            'source_type'           => 'manual',
        ];
        $data['trust_score']         = TrustScore::compute($data);
        $data['quality_score']       = TrustScore::quality($data);
        $data['verification_status'] = $data['trust_score'] >= 70 ? 'verified' : ($data['trust_score'] >= 40 ? 'review' : 'unverified');
        $data['status']              = 'published';
        Dedupe::ensureSchema();
        $data['dedupe_key']      = Dedupe::key((string) $data['name']);
        $data['slug']            = self::uniqueSlug(slugify((string) $data['name']));
        $data['discovered_at']   = gmdate('Y-m-d H:i:s');
        $data['last_checked_at'] = gmdate('Y-m-d H:i:s');
        $data['last_updated']    = gmdate('Y-m-d H:i:s');

        $id = Database::insert('software', array_filter($data, static fn($v) => $v !== null));
        foreach ($osIds as $osId) {
            try {
                Database::run('INSERT IGNORE INTO software_operating_systems (software_id, os_id) VALUES (:s, :o)', ['s' => $id, 'o' => $osId]);
            } catch (\Throwable $e) {}
        }
        if (!empty($d['screenshot'])) {
            try {
                Database::run('INSERT INTO software_screenshots (software_id, url, sort_order) VALUES (:s, :u, 1)', ['s' => $id, 'u' => $d['screenshot']]);
            } catch (\Throwable $e) {}
        }
        return $id;
    }

    /** Extract basic details from a software's official web page. */
    public static function extractFromUrl(string $url): ?array
    {
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $resp = Http::get($url, ['Accept: text/html'], 12);
        if ($resp['status'] < 200 || $resp['status'] >= 400 || $resp['body'] === '') {
            return null;
        }
        $html = $resp['body'];
        $meta = static function (string $pat) use ($html): string {
            return preg_match($pat, $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5) : '';
        };
        $title = '';
        if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
            $title = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5);
        }
        $ogTitle = $meta('~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)~i');
        $desc = $meta('~<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)~i');
        if ($desc === '') {
            $desc = $meta('~<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)~i');
        }
        $img = $meta('~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)~i');
        $host = (string) (parse_url($resp['effective_url'] ?: $url, PHP_URL_HOST) ?: '');
        $devHost = preg_replace('~^www\.~', '', $host) ?? $host;

        $name = trim((string) (preg_split('~[|\x{2013}\x{2014}:\-]~u', $ogTitle ?: $title)[0] ?? ''));
        if ($name === '') {
            $name = $devHost;
        }
        if ($name === '') {
            return null;
        }
        return [
            'name'                  => mb_substr($name, 0, 120),
            'developer_name'        => $devHost,
            'official_website'      => $url,
            'official_download_url' => $url,
            'short_description'     => str_excerpt($desc, 300),
            'long_description'      => $desc ?: null,
            'logo'                  => $host ? 'https://www.google.com/s2/favicons?domain=' . $host . '&sz=128' : null,
            'screenshot'            => $img ?: null,
            'source'                => 'url',
        ];
    }

    private static function uniqueSlug(string $base): string
    {
        $base = $base ?: 'app';
        $slug = $base;
        $i = 2;
        while (Database::scalar('SELECT id FROM software WHERE slug = :s', ['s' => $slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
