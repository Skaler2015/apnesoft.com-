<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Records verification / update events per software and exposes a public,
 * readable timeline. Every entry reflects a real action (published, details
 * verified, version changed, link checked) — no fabricated history.
 */
final class VerificationLog
{
    public static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            Database::run(
                'CREATE TABLE IF NOT EXISTS software_verification_events (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    software_id INT UNSIGNED NOT NULL,
                    type VARCHAR(30) NOT NULL,
                    detail VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_sve_software (software_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable $e) {}
    }

    public static function log(int $softwareId, string $type, string $detail = ''): void
    {
        if ($softwareId <= 0) {
            return;
        }
        self::ensureTable();
        try {
            Database::run(
                'INSERT INTO software_verification_events (software_id, type, detail, created_at) VALUES (:s, :t, :d, :c)',
                ['s' => $softwareId, 't' => mb_substr($type, 0, 30), 'd' => mb_substr($detail, 0, 255) ?: null, 'c' => gmdate('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {}
    }

    /** @return array<int,array<string,mixed>> newest first */
    public static function recent(int $softwareId, int $limit = 12): array
    {
        self::ensureTable();
        try {
            return Database::all(
                'SELECT type, detail, created_at FROM software_verification_events
                 WHERE software_id = :s ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(50, $limit)),
                ['s' => $softwareId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}
