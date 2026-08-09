<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Services\DiscoveryEngine;
use App\Services\JobRunner;

$result = JobRunner::run('github_sync', 'GitHub Sync', fn() => DiscoveryEngine::runAll('github_api'));
cron_out('github_sync', $result);
