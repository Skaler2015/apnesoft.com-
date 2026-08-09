<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Services\DiscoveryEngine;
use App\Services\JobRunner;

// Runs all due sources through the discovery pipeline.
$result = JobRunner::run('discover', 'Discovery', fn() => DiscoveryEngine::runAll());
cron_out('discover', $result);
