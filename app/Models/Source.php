<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

final class Source
{
    public static function all(): array
    {
        return Database::all('SELECT * FROM software_sources ORDER BY priority DESC, name');
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM software_sources WHERE id = :id', ['id' => $id]);
    }

    public static function due(string $type = null): array
    {
        $sql = 'SELECT * FROM software_sources WHERE status = "active"
                AND (next_sync IS NULL OR next_sync <= NOW())';
        $params = [];
        if ($type !== null) {
            $sql .= ' AND source_type = :t';
            $params['t'] = $type;
        }
        $sql .= ' ORDER BY priority DESC';
        return Database::all($sql, $params);
    }

    public static function apiKey(array $source): ?string
    {
        if (empty($source['api_key_enc'])) {
            return null;
        }
        return Crypto::decrypt($source['api_key_enc']);
    }

    public static function markSynced(int $id, bool $ok, ?string $error = null): void
    {
        $freq = (int) (Database::scalar('SELECT crawl_frequency FROM software_sources WHERE id = :id', ['id' => $id]) ?: 1440);
        $next = gmdate('Y-m-d H:i:s', time() + $freq * 60);
        if ($ok) {
            Database::update('software_sources', [
                'last_sync'   => gmdate('Y-m-d H:i:s'),
                'next_sync'   => $next,
                'error_count' => 0,
                'last_error'  => null,
            ], ['id' => $id]);
        } else {
            Database::run(
                'UPDATE software_sources SET error_count = error_count + 1, last_error = :e, next_sync = :n WHERE id = :id',
                ['e' => $error, 'n' => $next, 'id' => $id]
            );
        }
    }
}
