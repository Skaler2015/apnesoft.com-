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
