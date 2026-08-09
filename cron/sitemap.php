<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Models\Notification;
use App\Services\JobRunner;
use App\Services\Sitemap;

$result = JobRunner::run('sitemap', 'Sitemap Generator', function () {
    $counts = Sitemap::generateAll();
    Notification::push('sitemap', 'Sitemap regenerated', json_encode($counts), 'success');
    return ['processed' => array_sum($counts), 'updated' => array_sum($counts)];
});
cron_out('sitemap', $result);
