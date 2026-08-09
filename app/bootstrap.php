<?php
/**
 * Application bootstrap: autoloader, config, error handling, DB, helpers.
 * Included by the web front controller (public/index.php) and all cron scripts.
 */

declare(strict_types=1);

define('SH_START', microtime(true));

$root = dirname(__DIR__);

// --- PSR-4-ish autoloader (App\ => app/) -------------------------------------
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// --- Configuration -----------------------------------------------------------
$config = require $root . '/config/config.php';
\App\Core\Config::load($config);

// --- Error handling ----------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', \App\Core\Config::get('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $root . '/storage/logs/php-error.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    \App\Core\Logger::error("PHP $severity: $message in $file:$line");
    if (\App\Core\Config::get('app.debug')) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

// --- Timezone ----------------------------------------------------------------
date_default_timezone_set('UTC');

// --- Helper functions --------------------------------------------------------
require $root . '/app/Support/helpers.php';
