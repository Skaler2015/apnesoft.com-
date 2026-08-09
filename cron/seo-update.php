<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Core\Database;
use App\Services\JobRunner;
use App\Services\Seo;

$result = JobRunner::run('seo_update', 'SEO Generator', function () {
    $rows = Database::all('SELECT id FROM software WHERE status = "published"');
    foreach ($rows as $r) {
        Seo::generateForSoftware((int) $r['id']);
    }
    return ['processed' => count($rows), 'updated' => count($rows)];
});
cron_out('seo_update', $result);
