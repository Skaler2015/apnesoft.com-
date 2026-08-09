<?php
declare(strict_types=1);
require __DIR__ . '/_cli.php';

use App\Core\Config;
use App\Models\Notification;
use App\Services\JobRunner;

/**
 * Database backup via mysqldump (if available), with retention. Falls back to
 * a note when mysqldump is not present on the host.
 */
$result = JobRunner::run('backup', 'Database Backup', function () {
    $db = Config::get('db');
    $dir = Config::get('paths.storage') . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/backup-' . gmdate('Ymd-His') . '.sql.gz';

    $which = @shell_exec('command -v mysqldump 2>/dev/null');
    if (!$which) {
        Notification::push('backup', 'Backup skipped', 'mysqldump not available on host.', 'warning');
        return ['processed' => 0, 'skipped' => 1];
    }

    $cmd = sprintf(
        'mysqldump --single-transaction --no-tablespaces -h%s -P%d -u%s %s %s 2>/dev/null | gzip > %s',
        escapeshellarg($db['host']),
        (int) $db['port'],
        escapeshellarg($db['user']),
        $db['pass'] !== '' ? '-p' . escapeshellarg($db['pass']) : '',
        escapeshellarg($db['name']),
        escapeshellarg($file)
    );
    @shell_exec($cmd);

    $ok = is_file($file) && filesize($file) > 0;

    // Retention: keep newest 14.
    $backups = glob($dir . '/backup-*.sql.gz') ?: [];
    rsort($backups);
    foreach (array_slice($backups, 14) as $old) {
        @unlink($old);
    }

    if ($ok) {
        Notification::push('backup', 'Backup completed', basename($file), 'success');
        return ['processed' => 1, 'created' => 1];
    }
    Notification::push('backup', 'Backup failed', 'mysqldump produced no output.', 'error');
    return ['processed' => 1, 'failed' => 1];
});
cron_out('backup', $result);
