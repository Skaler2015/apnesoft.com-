<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\Source;
use App\Support\Http;

/**
 * GitHub REST integration for open-source software discovery + version tracking.
 * A github_api source stores a list of "owner/repo" targets in its config JSON:
 *   {"repos": ["videolan/vlc", "obsproject/obs-studio"], "min_stars": 200}
 */
final class GitHubService
{
    private const API = 'https://api.github.com';

    /**
     * @return array{processed:int, created:int, updated:int, skipped:int, failed:int}
     */
    public static function sync(array $source): array
    {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $config = self::config($source);
        $repos = $config['repos'] ?? [];
        $minStars = (int) ($config['min_stars'] ?? 0);
        $token = Source::apiKey($source) ?: (string) Config::get('integrations.github_token', '');

        foreach ($repos as $repo) {
            $stats['processed']++;
            $dto = self::fetchRepo($repo, $token, $source, $minStars);
            if ($dto === null) {
                $stats['failed']++;
                continue;
            }
            if ($dto === false) {
                $stats['skipped']++; // failed a quality gate
                continue;
            }
            $result = Ingest::process($dto);
            self::attachOs($result['software_id'] ?? null, $dto);
            match ($result['action']) {
                'published', 'review' => $stats['created']++,
                'updated', 'refreshed' => $stats['updated']++,
                default => $stats['skipped']++,
            };
        }

        return $stats;
    }

    /**
     * @return array|false|null  DTO, false=quality-reject, null=fetch error
     */
    public static function fetchRepo(string $repo, string $token, array $source, int $minStars)
    {
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $repoResp = Http::get(self::API . '/repos/' . $repo, $headers);
        if ($repoResp['status'] !== 200) {
            return null;
        }
        $r = json_decode($repoResp['body'], true);
        if (!is_array($r)) {
            return null;
        }

        // Quality gates: don't publish arbitrary repos.
        if (($r['archived'] ?? false) || ($r['disabled'] ?? false)) {
            return false;
        }
        if ((int) ($r['stargazers_count'] ?? 0) < $minStars) {
            return false;
        }

        // Latest release (preferred) or fall back to tags.
        $release = self::latestRelease($repo, $headers);

        $version = $release['tag_name'] ?? '';
        $changelog = $release['body'] ?? '';
        $releaseDate = isset($release['published_at']) ? substr($release['published_at'], 0, 10) : null;
        $downloadUrl = self::pickAsset($release['assets'] ?? []) ?: ($r['html_url'] . '/releases/latest');

        $signals = ($r['description'] ?? '') . ' ' . implode(' ', $r['topics'] ?? []) . ' ' . ($r['language'] ?? '');
        $categoryId = Classifier::detectCategory($r['name'] ?? '', $signals);

        $osIds = Classifier::detectOsIds($signals, $changelog, implode(' ', array_map(
            fn($a) => $a['name'] ?? '', $release['assets'] ?? []
        )));
        $osLabel = Classifier::osLabel($osIds);

        return [
            'name'                  => $r['name'] ?? $repo,
            'developer_name'        => $r['owner']['login'] ?? '',
            'developer_website'     => $r['owner']['html_url'] ?? '',
            'official_website'      => $r['homepage'] ?: $r['html_url'],
            'official_download_url' => $downloadUrl,
            'short_description'     => str_excerpt($r['description'] ?? '', 300),
            'long_description'      => $r['description'] ?? '',
            'version'               => $version,
            'release_date'          => $releaseDate,
            'license_type'          => $r['license']['spdx_id'] ?? ($r['license']['name'] ?? null),
            'price_type'            => 'open_source',
            'is_open_source'        => 1,
            'operating_system'      => $osLabel,
            'category_id'           => $categoryId,
            'logo'                  => $r['owner']['avatar_url'] ?? null,
            'changelog'             => str_excerpt($changelog, 4000),
            'stars'                 => (int) ($r['stargazers_count'] ?? 0),
            'forks'                 => (int) ($r['forks_count'] ?? 0),
            'source_id'             => (int) $source['id'],
            'source_type'           => 'github_api',
            'source_url'            => $r['html_url'] ?? '',
            'external_ref'          => 'github:' . strtolower($repo),
            '_os_ids'               => $osIds,
        ];
    }

    private static function latestRelease(string $repo, array $headers): array
    {
        $resp = Http::get(self::API . '/repos/' . $repo . '/releases/latest', $headers);
        if ($resp['status'] === 200) {
            $data = json_decode($resp['body'], true);
            if (is_array($data)) {
                return $data;
            }
        }
        // Fall back to newest tag.
        $tags = Http::get(self::API . '/repos/' . $repo . '/tags?per_page=1', $headers);
        if ($tags['status'] === 200) {
            $data = json_decode($tags['body'], true);
            if (!empty($data[0]['name'])) {
                return ['tag_name' => $data[0]['name']];
            }
        }
        return [];
    }

    private static function pickAsset(array $assets): ?string
    {
        // Prefer common installer types.
        $priority = ['.msi', '.exe', '.dmg', '.pkg', '.appimage', '.deb', '.rpm', '.zip', '.tar.gz'];
        foreach ($priority as $ext) {
            foreach ($assets as $a) {
                if (isset($a['name']) && str_ends_with(strtolower($a['name']), $ext)) {
                    return $a['browser_download_url'] ?? null;
                }
            }
        }
        return $assets[0]['browser_download_url'] ?? null;
    }

    private static function attachOs(?int $softwareId, array $dto): void
    {
        if (!$softwareId || empty($dto['_os_ids'])) {
            return;
        }
        foreach ($dto['_os_ids'] as $osId) {
            try {
                Database::run(
                    'INSERT IGNORE INTO software_operating_systems (software_id, os_id) VALUES (:s, :o)',
                    ['s' => $softwareId, 'o' => $osId]
                );
            } catch (\Throwable $e) {
            }
        }
    }

    private static function config(array $source): array
    {
        if (empty($source['config'])) {
            return [];
        }
        $c = json_decode($source['config'], true);
        return is_array($c) ? $c : [];
    }
}
