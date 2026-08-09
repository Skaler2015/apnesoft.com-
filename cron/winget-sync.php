<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Services\DiscoveryEngine;
use App\Services\JobRunner;

$result = JobRunner::run('winget_sync', 'Winget Sync', fn() => DiscoveryEngine::runAll('winget'));
cron_out('winget_sync', $result);
