<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Source;

/**
 * Orchestrates discovery across all due sources, dispatching each to the
 * appropriate source handler and aggregating stats.
 */
final class DiscoveryEngine
{
    public static function runAll(?string $onlyType = null): array
    {
        $totals = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $sources = Source::due($onlyType);

        foreach ($sources as $source) {
            $stats = self::runSource($source);
            foreach ($totals as $k => $_) {
                $totals[$k] += $stats[$k] ?? 0;
            }
        }
        return $totals;
    }

    public static function runSource(array $source): array
    {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        try {
            $stats = match ($source['source_type']) {
                'github_api'     => GitHubService::sync($source),
                'winget'         => WingetService::sync($source),
                'rss', 'atom'    => RssService::sync($source),
                'website'        => WebsiteCrawler::sync($source),
                default          => $stats, // manual / webhook handled elsewhere
            };
            Source::markSynced((int) $source['id'], true);
        } catch (\Throwable $e) {
            Source::markSynced((int) $source['id'], false, $e->getMessage());
            $stats['failed']++;
        }
        return $stats;
    }
}
