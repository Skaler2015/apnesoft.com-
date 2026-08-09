<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Very small file logger. Never throws (logging must not break the app).
 */
final class Logger
{
    public static function log(string $level, string $message): void
    {
        $dir = Config::get('paths.storage', dirname(__DIR__, 2) . '/storage') . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s: %s%s", gmdate('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
        @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $m): void { self::log('info', $m); }
    public static function warn(string $m): void { self::log('warning', $m); }
    public static function error(string $m): void { self::log('error', $m); }
}
