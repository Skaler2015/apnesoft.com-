<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Core\Database;
use App\Services\DiscoveryEngine;
use App\Services\JobRunner;
use App\Services\LinkChecker;
use App\Services\Seo;
use App\Services\Sitemap;

/**
 * Consolidated "heartbeat" cron. Designed to run hourly from a single cron job.
 * Each sub-task is cheap and self-throttling:
 *   - Discovery only processes sources whose next_sync is due (per crawl_frequency).
 *   - Link checker processes a small bounded batch each run.
 *   - Sitemap/SEO regenerate quickly for a modest catalog.
 * Heavier/rare tasks (cleanup) are gated to run about once per day.
 */

// 1. Discover + detect versions from all due sources.
$d = JobRunner::run('discover', 'Discovery', fn() => DiscoveryEngine::runAll());
cron_out('discover', $d);

// 2. Verify a small batch of download/official links.
$l = JobRunner::run('link_check', 'Link Checker', function () {
    $s = LinkChecker::run(20);
    return ['processed' => $s['processed'], 'updated' => $s['working'] + $s['redirect'],
            'failed' => $s['broken'], 'skipped' => $s['unavailable']];
});
cron_out('link_check', $l);

// 3. Rebuild sitemaps.
$s = JobRunner::run('sitemap', 'Sitemap', fn() => ['processed' => array_sum(Sitemap::generateAll())]);
cron_out('sitemap', $s);

// 4. Once per day (~between 02:00–02:59 UTC): refresh SEO + prune old logs.
if ((int) gmdate('G') === 2) {
    $seo = JobRunner::run('seo_update', 'SEO Generator', function () {
        $rows = Database::all('SELECT id FROM software WHERE status = "published"');
        foreach ($rows as $r) {
            Seo::generateForSoftware((int) $r['id']);
        }
        return ['processed' => count($rows)];
    });
    cron_out('seo_update', $seo);

    $c = JobRunner::run('cleanup', 'Cleanup', function () {
        $n = 0;
        $n += Database::run('DELETE FROM crawler_logs WHERE created_at < (NOW() - INTERVAL 60 DAY)')->rowCount();
        $n += Database::run('DELETE FROM verification_logs WHERE created_at < (NOW() - INTERVAL 60 DAY)')->rowCount();
        $n += Database::run('DELETE FROM page_views WHERE created_at < (NOW() - INTERVAL 90 DAY)')->rowCount();
        $n += Database::run('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 7 DAY)')->rowCount();
        return ['processed' => $n];
    });
    cron_out('cleanup', $c);
}

fwrite(STDOUT, "tick complete\n");
