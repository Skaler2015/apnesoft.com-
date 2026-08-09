<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

/**
 * File-based mutex to prevent simultaneous cron execution.
 * Usage: $lock = Lock::acquire('discover'); ... $lock->release();
 */

final class Lock
{
    private $handle;
    private string $file;

    private function __construct($handle, string $file)
    {
        $this->handle = $handle;
        $this->file = $file;
    }

    public static function acquire(string $name, int $staleSeconds = 3600): ?self
    {
        $dir = Config::get('paths.storage') . '/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $name) . '.lock';

        // Clear a stale lock left by a crashed process.
        if (is_file($file) && (time() - filemtime($file)) > $staleSeconds) {
            @unlink($file);
        }

        $handle = fopen($file, 'c');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null; // already running
        }
        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid());
        fflush($handle);
        return new self($handle, $file);
    }

    public function release(): void
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            @unlink($this->file);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
