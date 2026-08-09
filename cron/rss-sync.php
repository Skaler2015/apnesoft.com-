<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Services\DiscoveryEngine;
use App\Services\JobRunner;

$result = JobRunner::run('rss_sync', 'RSS Sync', function () {
    $a = DiscoveryEngine::runAll('rss');
    $b = DiscoveryEngine::runAll('atom');
    foreach ($b as $k => $v) { $a[$k] = ($a[$k] ?? 0) + $v; }
    return $a;
});
cron_out('rss_sync', $result);
