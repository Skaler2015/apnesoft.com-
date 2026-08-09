<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Core\Database;
use App\Services\DiscoveryEngine;
use App\Services\JobRunner;
use App\Models\Source;

/**
 * Version checker: re-syncs sources that back existing software so the Version
 * Detection Engine (inside Ingest) can pick up new releases. This re-uses the
 * discovery handlers, which only bump a version when it is genuinely newer.
 */
$result = JobRunner::run('version_check', 'Version Checker', function () {
    $totals = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
    // Re-check active sources that have produced software.
    $sources = Database::all(
        'SELECT DISTINCT s.* FROM software_sources s
         JOIN software sw ON sw.source_id = s.id
         WHERE s.status = "active"'
    );
    foreach ($sources as $src) {
        $stats = DiscoveryEngine::runSource($src);
        Source::markSynced((int) $src['id'], true);
        foreach ($totals as $k => $_) { $totals[$k] += $stats[$k] ?? 0; }
    }
    return $totals;
});
cron_out('version_check', $result);
