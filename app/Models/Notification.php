<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Notification
{
    public static function push(string $type, string $title, string $body = '', string $level = 'info', ?string $entity = null, ?int $entityId = null): void
    {
        Database::insert('notifications', [
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'level'     => $level,
            'entity'    => $entity,
            'entity_id' => $entityId,
        ]);
    }

    public static function recent(int $limit = 20): array
    {
        return Database::all('SELECT * FROM notifications ORDER BY created_at DESC LIMIT ' . (int) $limit);
    }

    public static function unreadCount(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
    }

    public static function markAllRead(): void
    {
        Database::run('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
    }
}
