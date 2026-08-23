<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Background publishing queue. The admin can drop hundreds of names in and
 * close the tab; the hourly cron drains the queue a batch at a time, publishing
 * each entry via the Publisher. Powers "queue in background", ready-made packs,
 * AI category fill and the daily auto-publish.
 */
final class PublishQueue
{
    /** Create the queue table once, if missing. */
    public static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            Database::run(
                "CREATE TABLE IF NOT EXISTS publish_queue (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(300) NOT NULL,
                    url VARCHAR(700) NULL,
                    os_slug VARCHAR(20) NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    result VARCHAR(300) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    processed_at DATETIME NULL,
                    PRIMARY KEY (id),
                    KEY idx_pq_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {}
    }

    /**
     * Add items (names, or lines that are URLs) to the queue for a platform.
     * @param string[] $items
     * @return int number queued
     */
    public static function add(array $items, ?string $osSlug = null): int
    {
        self::ensureTable();
        $n = 0;
        foreach ($items as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            $isUrl = (bool) preg_match('~^https?://~i', $raw);
            Database::run(
                'INSERT INTO publish_queue (name, url, os_slug, status) VALUES (:n, :u, :o, "pending")',
                ['n' => mb_substr($isUrl ? $raw : $raw, 0, 300), 'u' => $isUrl ? $raw : null, 'o' => $osSlug]
            );
            $n++;
        }
        return $n;
    }

    /** @return array{pending:int, done:int, duplicate:int, failed:int} */
    public static function stats(): array
    {
        self::ensureTable();
        $rows = Database::all('SELECT status, COUNT(*) c FROM publish_queue GROUP BY status');
        $out = ['pending' => 0, 'done' => 0, 'duplicate' => 0, 'failed' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * Process up to $limit pending entries (called by cron).
     * @return array{processed:int, published:int, duplicate:int, failed:int}
     */
    public static function processBatch(int $limit = 30): array
    {
        self::ensureTable();
        $rows = Database::all(
            'SELECT * FROM publish_queue WHERE status = "pending" ORDER BY id ASC LIMIT ' . max(1, min(200, $limit))
        );
        $published = $duplicate = $failed = 0;
        foreach ($rows as $r) {
            try {
                $res = Publisher::publish((string) $r['name'], $r['url'] ?: null, $r['os_slug'] ?: null);
                $status = $res['status'] === 'published' ? 'done' : ($res['status'] === 'duplicate' ? 'duplicate' : 'failed');
                if ($status === 'done') {
                    $published++;
                } elseif ($status === 'duplicate') {
                    $duplicate++;
                } else {
                    $failed++;
                }
                Database::run('UPDATE publish_queue SET status = :s, result = :r, processed_at = NOW() WHERE id = :id',
                    ['s' => $status, 'r' => mb_substr($res['status'], 0, 300), 'id' => $r['id']]);
            } catch (\Throwable $e) {
                $failed++;
                Database::run('UPDATE publish_queue SET status = "failed", result = :r, processed_at = NOW() WHERE id = :id',
                    ['r' => mb_substr($e->getMessage(), 0, 300), 'id' => $r['id']]);
            }
        }
        // Keep the table tidy: drop finished rows older than 3 days.
        try {
            Database::run('DELETE FROM publish_queue WHERE status <> "pending" AND processed_at < (NOW() - INTERVAL 3 DAY)');
        } catch (\Throwable $e) {}

        return ['processed' => count($rows), 'published' => $published, 'duplicate' => $duplicate, 'failed' => $failed];
    }

    public static function pending(): int
    {
        self::ensureTable();
        return (int) Database::scalar('SELECT COUNT(*) FROM publish_queue WHERE status = "pending"');
    }

    public static function clearPending(): void
    {
        self::ensureTable();
        Database::run('DELETE FROM publish_queue WHERE status = "pending"');
    }
}
