<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Support\Http;

/**
 * Bulk-imports real, popular open-source software from the GitHub Search API.
 * Only genuine repositories with real metadata (name, description, stars,
 * license, official URL) are published — never fabricated entries.
 *
 * Progress is cursor-based so the import can be driven a batch at a time from
 * the browser (auto-refresh) or cron, staying within API rate limits and PHP
 * execution limits.
 */
final class BulkImport
{
    /** Varied searches so the catalog spans categories/languages, not just one niche. */
    private const QUERIES = [
        'stars:>1500 topic:desktop',
        'stars:>1500 topic:electron-app',
        'stars:>1000 topic:cross-platform',
        'stars:>1000 topic:productivity',
        'stars:>1000 topic:editor',
        'stars:>1000 topic:text-editor',
        'stars:>1000 topic:ide',
        'stars:>1000 topic:terminal',
        'stars:>1000 topic:cli',
        'stars:>1000 topic:command-line',
        'stars:>1000 topic:security',
        'stars:>1000 topic:privacy',
        'stars:>1000 topic:vpn',
        'stars:>1000 topic:password-manager',
        'stars:>1000 topic:media-player',
        'stars:>1000 topic:video',
        'stars:>1000 topic:video-editor',
        'stars:>1000 topic:audio',
        'stars:>1000 topic:music',
        'stars:>1000 topic:screenshot',
        'stars:>1000 topic:screen-recorder',
        'stars:>1000 topic:image-editor',
        'stars:>1000 topic:pdf',
        'stars:>1000 topic:markdown',
        'stars:>1000 topic:note-taking',
        'stars:>1000 topic:notes',
        'stars:>1000 topic:backup',
        'stars:>1000 topic:file-manager',
        'stars:>1000 topic:download-manager',
        'stars:>1000 topic:browser',
        'stars:>1000 topic:email',
        'stars:>1000 topic:chat',
        'stars:>1000 topic:messaging',
        'stars:>1000 topic:remote-desktop',
        'stars:>1000 topic:torrent',
        'stars:>1000 topic:emulator',
        'stars:>1500 topic:game',
        'stars:>1000 topic:launcher',
        'stars:>1000 topic:utility',
        'stars:>2000 language:C++',
        'stars:>2000 language:C',
        'stars:>2000 language:C#',
        'stars:>2000 language:Rust',
        'stars:>3000 language:Go',
        'stars:>4000 language:Python',
        'stars:>6000 language:JavaScript',
        'stars:>4000 language:TypeScript',
        'stars:>2000 language:Java',
        'stars:>1500 language:Swift',
        'stars:>1500 language:Kotlin',
        'stars:>1500 language:Dart',
        'stars:>1000 language:Lua',
    ];

    private const PER_PAGE = 100;
    private const MAX_PAGE = 10; // GitHub search returns at most 1000 results per query

    /**
     * Run one batch (a single search page) from the current cursor.
     * @return array{created:int, skipped:int, failed:int, done:int, target:int, finished:bool, message:string}
     */
    public static function runBatch(): array
    {
        $target = self::intSetting('bulk_target', 2000);
        $done   = self::intSetting('bulk_done', 0);
        $qi     = self::intSetting('bulk_qi', 0);
        $page   = self::intSetting('bulk_page', 1);

        if ($done >= $target) {
            return self::result(0, 0, 0, $done, $target, true, 'Target reached.');
        }
        if ($qi >= count(self::QUERIES)) {
            return self::result(0, 0, 0, $done, $target, true, 'All sources exhausted.');
        }

        $token = (string) Config::get('integrations.github_token', '');
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $query = self::QUERIES[$qi];
        $url = 'https://api.github.com/search/repositories?q=' . rawurlencode($query)
            . '&sort=stars&order=desc&per_page=' . self::PER_PAGE . '&page=' . $page;

        $resp = Http::get($url, $headers, 25);

        // Rate limited or transient error: keep the cursor, ask caller to retry.
        if ($resp['status'] === 403 || $resp['status'] === 429) {
            return self::result(0, 0, 0, $done, $target, false, 'GitHub rate limit — pausing briefly.');
        }
        if ($resp['status'] !== 200) {
            // Skip this query on hard error.
            self::advanceQuery();
            return self::result(0, 0, 0, $done, $target, false, 'Source error, moving on…');
        }

        $data = json_decode($resp['body'], true);
        $items = $data['items'] ?? [];

        $created = $skipped = $failed = 0;
        foreach ($items as $repo) {
            if ($done + $created >= $target) {
                break;
            }
            try {
                $r = self::importRepo($repo);
                $r === 'created' ? $created++ : $skipped++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $done += $created;
        self::setSetting('bulk_done', (string) $done);

        // Advance cursor: next page, or next query when this one is exhausted.
        if (empty($items) || $page >= self::MAX_PAGE) {
            self::advanceQuery();
        } else {
            self::setSetting('bulk_page', (string) ($page + 1));
        }

        $finished = $done >= $target;
        return self::result($created, $skipped, $failed, $done, $target, $finished,
            $finished ? 'Done!' : 'Imported ' . $created . ' this batch.');
    }

    /**
     * Continuous, never-ending discovery for the hourly cron. Imports up to
     * $maxNew genuinely new software per run using a wrapping cursor, so the
     * catalog keeps growing automatically over time. Self-throttling and
     * bounded so it never exceeds PHP/API limits.
     *
     * @return array{created:int, skipped:int, scanned:int}
     */
    public static function runContinuous(int $maxNew = 250, int $maxPages = 6): array
    {
        if (self::intSetting('auto_discovery', 1) !== 1) {
            return ['created' => 0, 'skipped' => 0, 'scanned' => 0];
        }

        $token = (string) Config::get('integrations.github_token', '');
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $qi   = self::intSetting('disc_qi', 0);
        $page = self::intSetting('disc_page', 1);
        $created = $skipped = $scanned = 0;
        $queryCount = count(self::QUERIES);

        for ($p = 0; $p < $maxPages && $created < $maxNew; $p++) {
            if ($qi >= $queryCount) {          // wrap around and rescan for fresh repos
                $qi = 0;
                $page = 1;
            }
            $items = self::fetchPage(self::QUERIES[$qi], $page, $headers);
            if ($items === null) {
                break;                          // rate-limited or error: stop this run
            }
            foreach ($items as $repo) {
                $scanned++;
                try {
                    self::importRepo($repo) === 'created' ? $created++ : $skipped++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
                if ($created >= $maxNew) {
                    break;
                }
            }
            if (empty($items) || $page >= self::MAX_PAGE) {
                $qi++;
                $page = 1;
            } else {
                $page++;
            }
        }

        self::setSetting('disc_qi', (string) $qi);
        self::setSetting('disc_page', (string) $page);

        return ['created' => $created, 'skipped' => $skipped, 'scanned' => $scanned];
    }

    /** Fetch one GitHub search page; null on rate-limit/error. */
    private static function fetchPage(string $query, int $page, array $headers): ?array
    {
        $url = 'https://api.github.com/search/repositories?q=' . rawurlencode($query)
            . '&sort=stars&order=desc&per_page=' . self::PER_PAGE . '&page=' . $page;
        $resp = Http::get($url, $headers, 25);
        if ($resp['status'] !== 200) {
            return null;
        }
        $data = json_decode($resp['body'], true);
        return is_array($data['items'] ?? null) ? $data['items'] : [];
    }

    public static function reset(int $target): void
    {
        self::setSetting('bulk_target', (string) $target);
        self::setSetting('bulk_done', '0');
        self::setSetting('bulk_qi', '0');
        self::setSetting('bulk_page', '1');
    }

    public static function progress(): array
    {
        return [
            'done'   => self::intSetting('bulk_done', 0),
            'target' => self::intSetting('bulk_target', 2000),
        ];
    }

    /** @return string 'created'|'skipped' */
    private static function importRepo(array $repo): string
    {
        $full = strtolower((string) ($repo['full_name'] ?? ''));
        if ($full === '' || empty($repo['name'])) {
            return 'skipped';
        }
        $ref = 'github:' . $full;

        if (Database::scalar('SELECT id FROM software WHERE external_ref = :r', ['r' => $ref])) {
            return 'skipped';
        }

        $signals = ($repo['description'] ?? '') . ' ' . implode(' ', $repo['topics'] ?? []) . ' ' . ($repo['language'] ?? '');
        $categoryId = Classifier::detectCategory((string) $repo['name'], $signals) ?? self::defaultCategory();
        $osIds = Classifier::detectOsIds($signals);
        $osLabel = $osIds ? Classifier::osLabel($osIds) : 'Windows, macOS, Linux';

        $html = (string) ($repo['html_url'] ?? '');
        $data = [
            'name'                  => (string) $repo['name'],
            'developer_name'        => (string) ($repo['owner']['login'] ?? ''),
            'developer_website'     => (string) ($repo['owner']['html_url'] ?? ''),
            'official_website'      => $repo['homepage'] ?: $html,
            'official_download_url' => $html !== '' ? $html . '/releases' : null,
            'short_description'     => str_excerpt((string) ($repo['description'] ?? ''), 300),
            'long_description'      => (string) ($repo['description'] ?? ''),
            'license_type'          => $repo['license']['spdx_id'] ?? ($repo['license']['name'] ?? null),
            'price_type'            => 'open_source',
            'is_open_source'        => 1,
            'operating_system'      => $osLabel,
            'category_id'           => $categoryId,
            'logo'                  => $repo['owner']['avatar_url'] ?? null,
            'stars'                 => (int) ($repo['stargazers_count'] ?? 0),
            'forks'                 => (int) ($repo['forks_count'] ?? 0),
            'source_type'           => 'github_api',
            'source_url'            => $html,
            'external_ref'          => $ref,
            'last_checked_at'       => gmdate('Y-m-d H:i:s'),
        ];
        $data['trust_score']         = TrustScore::compute($data);
        $data['quality_score']       = TrustScore::quality($data);
        $data['verification_status'] = 'verified';
        $data['status']              = 'published';
        $data['discovered_at']       = gmdate('Y-m-d H:i:s');
        $data['last_updated']        = isset($repo['pushed_at']) ? substr((string) $repo['pushed_at'], 0, 10) : gmdate('Y-m-d');
        $data['slug']                = self::uniqueSlug(\slugify((string) $repo['name']));

        $id = Database::insert('software', array_filter($data, static fn($v) => $v !== null));

        foreach ($osIds as $osId) {
            try {
                Database::run('INSERT IGNORE INTO software_operating_systems (software_id, os_id) VALUES (:s, :o)',
                    ['s' => $id, 'o' => $osId]);
            } catch (\Throwable $e) {
            }
        }
        Seo::generateForSoftware($id);
        return 'created';
    }

    private static function defaultCategory(): ?int
    {
        $id = Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => 'developer-tools']);
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

    private static function advanceQuery(): void
    {
        self::setSetting('bulk_qi', (string) (self::intSetting('bulk_qi', 0) + 1));
        self::setSetting('bulk_page', '1');
    }

    private static function intSetting(string $k, int $default): int
    {
        $v = Database::scalar('SELECT `value` FROM settings WHERE `key` = :k', ['k' => $k]);
        return is_numeric($v) ? (int) $v : $default;
    }

    private static function setSetting(string $k, string $v): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`, `group`, `type`) VALUES (:k, :v, "bulk", "int")
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['k' => $k, 'v' => $v]
        );
    }

    private static function result(int $c, int $s, int $f, int $done, int $target, bool $finished, string $msg): array
    {
        return ['created' => $c, 'skipped' => $s, 'failed' => $f, 'done' => $done,
                'target' => $target, 'finished' => $finished, 'message' => $msg];
    }
}
