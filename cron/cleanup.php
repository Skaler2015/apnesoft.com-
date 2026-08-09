<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Core\Database;
use App\Services\JobRunner;

/**
 * Prune old logs / analytics to keep tables lean on shared hosting.
 */
$result = JobRunner::run('cleanup', 'Cleanup', function () {
    $deleted = 0;
    $deleted += Database::run('DELETE FROM crawler_logs WHERE created_at < (NOW() - INTERVAL 60 DAY)')->rowCount();
    $deleted += Database::run('DELETE FROM verification_logs WHERE created_at < (NOW() - INTERVAL 60 DAY)')->rowCount();
    $deleted += Database::run('DELETE FROM page_views WHERE created_at < (NOW() - INTERVAL 90 DAY)')->rowCount();
    $deleted += Database::run('DELETE FROM search_queries WHERE created_at < (NOW() - INTERVAL 90 DAY)')->rowCount();
    $deleted += Database::run('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 7 DAY)')->rowCount();
    $deleted += Database::run('DELETE FROM notifications WHERE is_read = 1 AND created_at < (NOW() - INTERVAL 30 DAY)')->rowCount();
    return ['processed' => $deleted, 'updated' => $deleted];
});
cron_out('cleanup', $result);
