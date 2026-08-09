<?php

declare(strict_types=1);

/**
 * Shared cron bootstrap. Ensures scripts only run from CLI and loads the app.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Cron scripts must be run from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (!\App\Core\Config::get('automation.enabled', true)) {
    fwrite(STDOUT, "Automation is disabled (CRON_ENABLED=false). Exiting.\n");
    exit(0);
}

function cron_out(string $key, array $result): void
{
    fwrite(STDOUT, sprintf(
        "[%s] %s: status=%s processed=%d created=%d updated=%d skipped=%d failed=%d (%ds)\n",
        gmdate('Y-m-d H:i:s'), $key,
        $result['status'] ?? 'ok',
        $result['processed'] ?? 0, $result['created'] ?? 0, $result['updated'] ?? 0,
        $result['skipped'] ?? 0, $result['failed'] ?? 0, $result['duration'] ?? 0
    ));
}
