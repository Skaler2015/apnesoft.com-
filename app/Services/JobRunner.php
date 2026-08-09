<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Notification;
use App\Support\Lock;

/**
 * Wraps an automation job with locking, timing, crawler_logs, cron_jobs status,
 * and error notifications. Ensures no automation task ever silently fails.
 */
final class JobRunner
{
    /**
     * @param callable():array $task returns a stats array
     */
    public static function run(string $key, string $name, callable $task): array
    {
        $lock = Lock::acquire($key);
        if ($lock === null) {
            Logger::warn("Job $key skipped: already running");
            return ['status' => 'locked', 'message' => 'already running'];
        }

        $start = time();
        self::setStatus($key, 'running');

        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $status = 'ok';
        $message = '';

        try {
            $result = $task();
            if (is_array($result)) {
                $stats = array_merge($stats, $result);
            }
        } catch (\Throwable $e) {
            $status = 'error';
            $message = $e->getMessage();
            Logger::error("Job $key failed: " . $e->getMessage());
            Notification::push('source_failure', "Automation job failed: $name", $message, 'error');
        }

        $duration = time() - $start;

        // crawler log
        try {
            Database::insert('crawler_logs', [
                'job'         => $key,
                'status'      => $status,
                'processed'   => $stats['processed'] ?? 0,
                'created'     => $stats['created'] ?? 0,
                'updated'     => $stats['updated'] ?? 0,
                'skipped'     => $stats['skipped'] ?? 0,
                'failed'      => $stats['failed'] ?? 0,
                'message'     => $message,
                'started_at'  => gmdate('Y-m-d H:i:s', $start),
                'finished_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error("Could not write crawler log for $key: " . $e->getMessage());
        }

        self::finishStatus($key, $status, $duration, (int) ($stats['processed'] ?? 0), (int) ($stats['failed'] ?? 0));

        $lock->release();

        Logger::info(sprintf(
            'Job %s finished in %ds: processed=%d created=%d updated=%d skipped=%d failed=%d',
            $key, $duration, $stats['processed'] ?? 0, $stats['created'] ?? 0,
            $stats['updated'] ?? 0, $stats['skipped'] ?? 0, $stats['failed'] ?? 0
        ));

        return array_merge($stats, ['status' => $status, 'duration' => $duration, 'message' => $message]);
    }

    private static function setStatus(string $key, string $status): void
    {
        try {
            Database::run('UPDATE cron_jobs SET status = :s WHERE `key` = :k', ['s' => $status, 'k' => $key]);
        } catch (\Throwable $e) {
        }
    }

    private static function finishStatus(string $key, string $status, int $duration, int $processed, int $errors): void
    {
        try {
            Database::run(
                'UPDATE cron_jobs SET status = :s, last_run_at = NOW(), last_duration = :d,
                    last_processed = :p, last_errors = :e WHERE `key` = :k',
                ['s' => $status === 'ok' ? 'idle' : 'error', 'd' => $duration, 'p' => $processed, 'e' => $errors, 'k' => $key]
            );
        } catch (\Throwable $e) {
        }
    }
}
