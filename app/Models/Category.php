<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Category
{
    public static function all(): array
    {
        return Database::all('SELECT * FROM categories WHERE status = "active" ORDER BY sort_order, name');
    }

    /** Add the os_slug column once (existing categories become Windows). */
    public static function ensureScope(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $has = (int) Database::scalar(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'os_slug'"
            );
            if ($has === 0) {
                Database::run("ALTER TABLE categories ADD COLUMN os_slug VARCHAR(20) NULL");
                // All current data is Windows, so tag existing categories accordingly.
                Database::run("UPDATE categories SET os_slug = 'windows' WHERE os_slug IS NULL OR os_slug = ''");
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Categories for one platform panel (sorted by name). When $slug is empty
     * (no panel / "all"), every category is returned. $includeId keeps a given
     * category in the list even if it belongs to another platform (edit form).
     * @return array<int,array<string,mixed>>
     */
    public static function forPlatform(string $slug, ?int $includeId = null): array
    {
        self::ensureScope();
        if ($slug === '') {
            return Database::all('SELECT * FROM categories ORDER BY name');
        }
        $sql = 'SELECT * FROM categories WHERE (os_slug = :s OR os_slug IS NULL' . ($includeId ? ' OR id = :cid' : '') . ') ORDER BY name';
        $params = ['s' => $slug];
        if ($includeId) {
            $params['cid'] = $includeId;
        }
        return Database::all($sql, $params);
    }

    public static function roots(): array
    {
        return Database::all(
            'SELECT * FROM categories WHERE status = "active" AND parent_id IS NULL ORDER BY sort_order, name'
        );
    }

    public static function children(int $parentId): array
    {
        return Database::all(
            'SELECT * FROM categories WHERE status = "active" AND parent_id = :p ORDER BY sort_order, name',
            ['p' => $parentId]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::first('SELECT * FROM categories WHERE slug = :s', ['s' => $slug]);
    }

    public static function trending(int $limit = 8): array
    {
        return Database::all(
            'SELECT c.*, COUNT(s.id) AS software_count FROM categories c
             LEFT JOIN software s ON (s.category_id = c.id OR s.subcategory_id = c.id) AND s.status = "published"
             WHERE c.status = "active"
             GROUP BY c.id ORDER BY c.is_trending DESC, software_count DESC LIMIT ' . (int) $limit
        );
    }

    public static function withCounts(): array
    {
        return Database::all(
            'SELECT c.*, COUNT(s.id) AS software_count FROM categories c
             LEFT JOIN software s ON (s.category_id = c.id OR s.subcategory_id = c.id) AND s.status = "published"
             WHERE c.status = "active"
             GROUP BY c.id ORDER BY c.sort_order, c.name'
        );
    }
}
