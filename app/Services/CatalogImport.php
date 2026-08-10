<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Support\Http;

/**
 * Imports real software from official multi-platform app catalogs with public
 * APIs — Homebrew casks (macOS), Flathub (Linux) and F-Droid (Android).
 * Only genuine, authorized entries with official links are published; large
 * catalog files are cached to disk and imported a batch at a time via a cursor.
 */
final class CatalogImport
{
    private const SOURCES = ['homebrew', 'flathub', 'fdroid'];

    public static function sources(): array
    {
        return self::SOURCES;
    }

    /** Run one batch for the named source. */
    public static function run(string $source, int $maxNew = 150): array
    {
        return match ($source) {
            'homebrew' => self::homebrew($maxNew),
            'flathub'  => self::flathub($maxNew),
            'fdroid'   => self::fdroid($maxNew),
            default    => self::zero('unknown source'),
        };
    }

    /** Rotate through the catalogs (one per call) — used by the hourly cron. */
    public static function runRotating(int $maxNew = 150): array
    {
        $turn = self::intSetting('catalog_turn', 0) % count(self::SOURCES);
        self::setSetting('catalog_turn', (string) ($turn + 1));
        $source = self::SOURCES[$turn];
        $r = self::run($source, $maxNew);
        $r['source'] = $source;
        return $r;
    }

    // -- macOS: Homebrew casks -------------------------------------------------
    private static function homebrew(int $maxNew): array
    {
        $casks = self::cachedJson('homebrew_casks', 'https://formulae.brew.sh/api/cask.json');
        if (!is_array($casks)) {
            return self::zero('homebrew unavailable');
        }
        return self::importSlice('brew_off', $casks, $maxNew, static function ($c) {
            $token = $c['token'] ?? null;
            if (!$token) {
                return null;
            }
            $name = is_array($c['name'] ?? null) ? ($c['name'][0] ?? $token) : ($c['name'] ?? $token);
            return [
                'external_ref'          => 'brew:' . strtolower((string) $token),
                'name'                  => (string) $name,
                'developer_name'        => self::host((string) ($c['homepage'] ?? '')),
                'official_website'      => $c['homepage'] ?? null,
                'official_download_url' => $c['url'] ?? ($c['homepage'] ?? null),
                'short_description'     => str_excerpt((string) ($c['desc'] ?? ''), 300),
                'long_description'      => (string) ($c['desc'] ?? ''),
                'version'               => is_string($c['version'] ?? null) ? $c['version'] : null,
                'price_type'            => null,
                'is_open_source'        => 0,
                'os_slug'               => 'macos',
                'os_label'              => 'macOS',
                'signals'               => ($c['desc'] ?? '') . ' ' . $name,
            ];
        });
    }

    // -- Linux: Flathub --------------------------------------------------------
    private static function flathub(int $maxNew): array
    {
        $apps = self::cachedJson('flathub_apps', 'https://flathub.org/api/v1/apps');
        if (!is_array($apps)) {
            return self::zero('flathub unavailable');
        }
        return self::importSlice('flathub_off', $apps, $maxNew, static function ($a) {
            $id = $a['flatpakAppId'] ?? null;
            if (!$id) {
                return null;
            }
            return [
                'external_ref'          => 'flatpak:' . strtolower((string) $id),
                'name'                  => (string) ($a['name'] ?? $id),
                'developer_name'        => (string) ($a['developerName'] ?? ''),
                'official_website'      => 'https://flathub.org/apps/' . $id,
                'official_download_url' => 'https://flathub.org/apps/' . $id,
                'short_description'     => str_excerpt((string) ($a['summary'] ?? ''), 300),
                'long_description'      => (string) ($a['summary'] ?? ''),
                'version'               => is_string($a['currentReleaseVersion'] ?? null) ? $a['currentReleaseVersion'] : null,
                'price_type'            => 'free',
                'is_open_source'        => 0,
                'os_slug'               => 'linux',
                'os_label'              => 'Linux',
                'signals'               => ($a['summary'] ?? '') . ' ' . ($a['name'] ?? ''),
                'logo'                  => $a['iconDesktopUrl'] ?? null,
            ];
        });
    }

    // -- Android: F-Droid ------------------------------------------------------
    private static function fdroid(int $maxNew): array
    {
        $index = self::cachedJson('fdroid_index', 'https://f-droid.org/repo/index-v1.json', 86400, 60);
        $apps = is_array($index['apps'] ?? null) ? $index['apps'] : null;
        if (!is_array($apps)) {
            return self::zero('f-droid unavailable');
        }
        return self::importSlice('fdroid_off', $apps, $maxNew, static function ($a) {
            $pkg = $a['packageName'] ?? null;
            if (!$pkg) {
                return null;
            }
            $name = $a['name'] ?? ($a['localized']['en-US']['name'] ?? $pkg);
            $summary = $a['summary'] ?? ($a['localized']['en-US']['summary'] ?? '');
            return [
                'external_ref'          => 'fdroid:' . strtolower((string) $pkg),
                'name'                  => (string) $name,
                'developer_name'        => (string) ($a['authorName'] ?? ''),
                'official_website'      => $a['webSite'] ?: ($a['sourceCode'] ?? 'https://f-droid.org/packages/' . $pkg),
                'official_download_url' => 'https://f-droid.org/packages/' . $pkg,
                'short_description'     => str_excerpt((string) $summary, 300),
                'long_description'      => (string) $summary,
                'version'               => is_string($a['suggestedVersionName'] ?? null) ? $a['suggestedVersionName'] : null,
                'license_type'          => $a['license'] ?? null,
                'price_type'            => 'open_source',
                'is_open_source'        => 1,
                'os_slug'               => 'android',
                'os_label'              => 'Android',
                'signals'               => $summary . ' ' . implode(' ', (array) ($a['categories'] ?? [])),
            ];
        });
    }

    // -- shared import ---------------------------------------------------------

    /**
     * @param string $cursorKey settings key holding the offset into $list
     * @param array  $list      full catalog array
     * @param int    $maxNew    max new records to publish this run
     * @param callable $mapper  fn(item): ?array normalized dto
     */
    private static function importSlice(string $cursorKey, array $list, int $maxNew, callable $mapper): array
    {
        $off = self::intSetting($cursorKey, 0);
        $count = count($list);
        if ($off >= $count) {
            return ['created' => 0, 'skipped' => 0, 'scanned' => 0, 'message' => 'catalog complete'];
        }

        $created = $skipped = $scanned = 0;
        $i = $off;
        // Scan forward until we publish $maxNew new items or reach the end.
        for (; $i < $count && $created < $maxNew; $i++) {
            $scanned++;
            $dto = $mapper($list[$i]);
            if ($dto === null) {
                $skipped++;
                continue;
            }
            try {
                self::store($dto) ? $created++ : $skipped++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }
        self::setSetting($cursorKey, (string) $i);

        return ['created' => $created, 'skipped' => $skipped, 'scanned' => $scanned,
                'message' => 'imported ' . $created];
    }

    /** @return bool true if newly created */
    private static function store(array $d): bool
    {
        if (empty($d['name']) || empty($d['external_ref'])) {
            return false;
        }
        if (Database::scalar('SELECT id FROM software WHERE external_ref = :r', ['r' => $d['external_ref']])) {
            return false;
        }

        $categoryId = Classifier::detectCategory($d['name'], (string) ($d['signals'] ?? ''));
        if ($categoryId === null) {
            $categoryId = self::defaultCategory();
        }

        $record = [
            'name'                  => $d['name'],
            'developer_name'        => $d['developer_name'] ?: null,
            'official_website'      => $d['official_website'] ?: null,
            'official_download_url' => $d['official_download_url'] ?: null,
            'short_description'     => $d['short_description'] ?: null,
            'long_description'      => $d['long_description'] ?: null,
            'version'               => $d['version'] ?: null,
            'license_type'          => $d['license_type'] ?? null,
            'price_type'            => $d['price_type'] ?? null,
            'is_open_source'        => (int) ($d['is_open_source'] ?? 0),
            'operating_system'      => $d['os_label'] ?? null,
            'category_id'           => $categoryId,
            'logo'                  => $d['logo'] ?? null,
            'source_type'           => explode(':', $d['external_ref'])[0],
            'source_url'            => $d['official_website'] ?: null,
            'external_ref'          => $d['external_ref'],
            'last_checked_at'       => gmdate('Y-m-d H:i:s'),
            'discovered_at'         => gmdate('Y-m-d H:i:s'),
            'last_updated'          => gmdate('Y-m-d'),
        ];
        $record['trust_score']         = TrustScore::compute($record);
        $record['quality_score']       = TrustScore::quality($record);
        $record['verification_status'] = $record['trust_score'] >= 70 ? 'verified' : 'review';
        $record['status']              = 'published';
        $record['slug']                = self::uniqueSlug(\slugify((string) $d['name']));

        $id = Database::insert('software', array_filter($record, static fn($v) => $v !== null));

        // OS mapping.
        $osId = Database::scalar('SELECT id FROM operating_systems WHERE slug = :s', ['s' => $d['os_slug'] ?? '']);
        if ($osId) {
            try {
                Database::run('INSERT IGNORE INTO software_operating_systems (software_id, os_id) VALUES (:s, :o)',
                    ['s' => $id, 'o' => (int) $osId]);
            } catch (\Throwable $e) {
            }
        }
        Seo::generateForSoftware($id);
        return true;
    }

    // -- helpers ---------------------------------------------------------------

    private static function cachedJson(string $key, string $url, int $ttl = 86400, int $timeout = 40): ?array
    {
        $dir = Config::get('paths.storage') . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . preg_replace('/[^a-z0-9]/i', '_', $key) . '.json';

        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $resp = Http::get($url, ['Accept: application/json'], $timeout);
        if ($resp['status'] === 200 && $resp['body'] !== '') {
            $data = json_decode($resp['body'], true);
            if (is_array($data)) {
                @file_put_contents($file, $resp['body']);
                return $data;
            }
        }
        // Fall back to any stale cache.
        if (is_file($file)) {
            $stale = json_decode((string) file_get_contents($file), true);
            if (is_array($stale)) {
                return $stale;
            }
        }
        return null;
    }

    private static function defaultCategory(): ?int
    {
        $id = Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => 'utilities']);
        return $id ? (int) $id : null;
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

    private static function host(string $url): string
    {
        $h = parse_url($url, PHP_URL_HOST);
        return $h ? preg_replace('/^www\./', '', (string) $h) : '';
    }

    private static function intSetting(string $k, int $default): int
    {
        $v = Database::scalar('SELECT `value` FROM settings WHERE `key` = :k', ['k' => $k]);
        return is_numeric($v) ? (int) $v : $default;
    }

    private static function setSetting(string $k, string $v): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`, `group`, `type`) VALUES (:k, :v, "catalog", "int")
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['k' => $k, 'v' => $v]
        );
    }

    private static function zero(string $msg): array
    {
        return ['created' => 0, 'skipped' => 0, 'scanned' => 0, 'message' => $msg];
    }
}
