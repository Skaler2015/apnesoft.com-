<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Services\JobRunner;
use App\Services\LinkChecker;

$result = JobRunner::run('link_check', 'Link Checker', function () {
    $stats = LinkChecker::run(40);
    return [
        'processed' => $stats['processed'],
        'updated'   => $stats['working'] + $stats['redirect'],
        'failed'    => $stats['broken'],
        'skipped'   => $stats['unavailable'],
    ];
});
cron_out('link_check', $result);
