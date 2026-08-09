<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Query gateway for the software table. All methods return plain arrays.
 * Only 'published' rows are exposed to the public site.
 */
final class Software
{
    public const PUBLISHED = 'published';

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM software WHERE id = :id', ['id' => $id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::first('SELECT * FROM software WHERE slug = :slug', ['slug' => $slug]);
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        return Database::first(
            'SELECT * FROM software WHERE slug = :slug AND status = :st',
            ['slug' => $slug, 'st' => self::PUBLISHED]
        );
    }

    /** Popular (by views + download clicks). */
    public static function popular(int $limit = 12): array
    {
        return Database::all(
            'SELECT * FROM software WHERE status = :st
             ORDER BY (views + download_clicks * 2) DESC, trust_score DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED]
        );
    }

    public static function recentlyUpdated(int $limit = 12): array
    {
        return Database::all(
            'SELECT * FROM software WHERE status = :st AND last_updated IS NOT NULL
             ORDER BY last_updated DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED]
        );
    }

    public static function newest(int $limit = 12): array
    {
        return Database::all(
            'SELECT * FROM software WHERE status = :st
             ORDER BY COALESCE(discovered_at, created_at) DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED]
        );
    }

    public static function byPrice(string $priceType, int $limit = 12): array
    {
        return Database::all(
            'SELECT * FROM software WHERE status = :st AND price_type = :p
             ORDER BY (views + 1) DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED, 'p' => $priceType]
        );
    }

    public static function openSource(int $limit = 12): array
    {
        return Database::all(
            'SELECT * FROM software WHERE status = :st AND is_open_source = 1
             ORDER BY stars DESC, views DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED]
        );
    }

    public static function byOsSlug(string $osSlug, int $limit = 12): array
    {
        return Database::all(
            'SELECT s.* FROM software s
             JOIN software_operating_systems sos ON sos.software_id = s.id
             JOIN operating_systems o ON o.id = sos.os_id
             WHERE s.status = :st AND o.slug = :os
             ORDER BY s.views DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED, 'os' => $osSlug]
        );
    }

    public static function lowEndPc(int $maxRamMb = 4096, int $limit = 12): array
    {
        return Database::all(
            'SELECT * FROM software WHERE status = :st AND min_ram_mb IS NOT NULL AND min_ram_mb <= :ram
             ORDER BY min_ram_mb ASC, views DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED, 'ram' => $maxRamMb]
        );
    }

    /**
     * Filtered, paginated listing used by /software and category/os pages.
     * @return array{items:array, total:int}
     */
    public static function filter(array $f, int $page = 1, int $perPage = 24): array
    {
        $where = ['s.status = :st'];
        $params = ['st' => self::PUBLISHED];
        $joins = '';

        if (!empty($f['category_id'])) {
            $where[] = '(s.category_id = :cat OR s.subcategory_id = :cat)';
            $params['cat'] = (int) $f['category_id'];
        }
        if (!empty($f['os_slug'])) {
            $joins .= ' JOIN software_operating_systems sos ON sos.software_id = s.id
                        JOIN operating_systems o ON o.id = sos.os_id AND o.slug = :os';
            $params['os'] = $f['os_slug'];
        }
        if (!empty($f['price_type'])) {
            $where[] = 's.price_type = :price';
            $params['price'] = $f['price_type'];
        }
        if (!empty($f['open_source'])) {
            $where[] = 's.is_open_source = 1';
        }
        if (!empty($f['max_ram'])) {
            $where[] = 's.min_ram_mb IS NOT NULL AND s.min_ram_mb <= :ram';
            $params['ram'] = (int) $f['max_ram'];
        }
        if (!empty($f['architecture'])) {
            $where[] = 's.architecture = :arch';
            $params['arch'] = $f['architecture'];
        }
        if (!empty($f['trust_min'])) {
            $where[] = 's.trust_score >= :trust';
            $params['trust'] = (int) $f['trust_min'];
        }
        if (!empty($f['q'])) {
            $where[] = '(s.name LIKE :q OR s.short_description LIKE :q OR s.developer_name LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }

        $order = match ($f['sort'] ?? 'popular') {
            'newest'  => 's.discovered_at DESC',
            'updated' => 's.last_updated DESC',
            'name'    => 's.name ASC',
            'trust'   => 's.trust_score DESC',
            default   => '(s.views + s.download_clicks) DESC',
        };

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(DISTINCT s.id) FROM software s $joins WHERE $whereSql",
            $params
        );

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $items = Database::all(
            "SELECT DISTINCT s.* FROM software s $joins WHERE $whereSql
             ORDER BY $order LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Full-text + LIKE search with light natural-language normalisation.
     */
    public static function search(string $query, int $limit = 40): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        // Natural-language hints -> structured filters.
        $priceFilter = '';
        $params = ['st' => self::PUBLISHED];
        $normalized = strtolower($query);
        if (str_contains($normalized, 'free')) {
            $priceFilter = " AND (price_type IN ('free','open_source','freemium'))";
        }
        if (str_contains($normalized, 'open source') || str_contains($normalized, 'open-source')) {
            $priceFilter .= ' AND is_open_source = 1';
        }

        $like = '%' . $query . '%';
        $params['like'] = $like;

        // Prefer FULLTEXT relevance; fall back to LIKE ordering.
        $sql = "SELECT *,
                    MATCH(name, short_description, long_description) AGAINST (:q IN NATURAL LANGUAGE MODE) AS relevance
                FROM software
                WHERE status = :st $priceFilter
                  AND (MATCH(name, short_description, long_description) AGAINST (:q IN NATURAL LANGUAGE MODE)
                       OR name LIKE :like OR developer_name LIKE :like)
                ORDER BY relevance DESC, views DESC
                LIMIT " . (int) $limit;
        $params['q'] = $query;

        try {
            return Database::all($sql, $params);
        } catch (\Throwable $e) {
            // FULLTEXT may be unavailable on some engines — fall back to LIKE.
            return Database::all(
                "SELECT * FROM software WHERE status = :st $priceFilter
                 AND (name LIKE :like OR short_description LIKE :like OR developer_name LIKE :like)
                 ORDER BY views DESC LIMIT " . (int) $limit,
                ['st' => self::PUBLISHED, 'like' => $like]
            );
        }
    }

    public static function alternatives(int $softwareId, int $limit = 8): array
    {
        return Database::all(
            'SELECT s.*, a.similarity, a.reason FROM software_alternatives a
             JOIN software s ON s.id = a.alternative_id
             WHERE a.software_id = :id AND s.status = :st
             ORDER BY a.similarity DESC LIMIT ' . (int) $limit,
            ['id' => $softwareId, 'st' => self::PUBLISHED]
        );
    }

    /** Same-category software excluding self, as "similar". */
    public static function similar(array $software, int $limit = 6): array
    {
        if (empty($software['category_id'])) {
            return [];
        }
        return Database::all(
            'SELECT * FROM software WHERE status = :st AND category_id = :cat AND id <> :id
             ORDER BY views DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED, 'cat' => $software['category_id'], 'id' => $software['id']]
        );
    }

    public static function versions(int $softwareId): array
    {
        return Database::all(
            'SELECT * FROM software_versions WHERE software_id = :id ORDER BY is_current DESC, id DESC',
            ['id' => $softwareId]
        );
    }

    public static function features(int $softwareId, string $type = 'feature'): array
    {
        return Database::all(
            'SELECT label FROM software_features WHERE software_id = :id AND `type` = :t ORDER BY sort_order, id',
            ['id' => $softwareId, 't' => $type]
        );
    }

    public static function screenshots(int $softwareId): array
    {
        return Database::all(
            'SELECT * FROM software_screenshots WHERE software_id = :id ORDER BY sort_order, id',
            ['id' => $softwareId]
        );
    }

    public static function incrementViews(int $id): void
    {
        Database::run('UPDATE software SET views = views + 1 WHERE id = :id', ['id' => $id]);
    }

    public static function recentUpdates(int $limit = 40): array
    {
        return Database::all(
            'SELECT u.*, s.name, s.slug, s.logo FROM update_history u
             JOIN software s ON s.id = u.software_id
             WHERE s.status = :st ORDER BY u.created_at DESC LIMIT ' . (int) $limit,
            ['st' => self::PUBLISHED]
        );
    }
}
