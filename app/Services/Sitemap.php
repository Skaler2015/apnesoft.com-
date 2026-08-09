<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Generates segmented sitemaps into /public/sitemaps and a sitemap index.
 * Only canonical, indexable URLs are included.
 */
final class Sitemap
{
    public static function generateAll(): array
    {
        $dir = Config::get('paths.public') . '/sitemaps';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $counts = [
            'software'     => self::writeUrlset($dir . '/software.xml', self::softwareUrls()),
            'categories'   => self::writeUrlset($dir . '/categories.xml', self::categoryUrls()),
            'compare'      => self::writeUrlset($dir . '/compare.xml', self::compareUrls()),
            'alternatives' => self::writeUrlset($dir . '/alternatives.xml', self::alternativeUrls()),
        ];

        self::writeIndex(Config::get('paths.public') . '/sitemap.xml', array_keys($counts));
        return $counts;
    }

    private static function softwareUrls(): array
    {
        $rows = Database::all(
            'SELECT s.slug, s.updated_at FROM software s
             JOIN seo_metadata m ON m.entity_type = "software" AND m.entity_id = s.id
             WHERE s.status = "published" AND m.is_indexable = 1'
        );
        return array_map(fn($r) => [
            'loc' => base_url('/software/' . $r['slug']),
            'lastmod' => self::date($r['updated_at']),
            'priority' => '0.8',
        ], $rows);
    }

    private static function categoryUrls(): array
    {
        $rows = Database::all('SELECT slug FROM categories WHERE status = "active"');
        return array_map(fn($r) => [
            'loc' => base_url('/category/' . $r['slug']),
            'priority' => '0.6',
        ], $rows);
    }

    private static function compareUrls(): array
    {
        $rows = Database::all('SELECT slug, updated_at FROM software_comparisons WHERE is_indexable = 1');
        return array_map(fn($r) => [
            'loc' => base_url('/compare/' . $r['slug']),
            'lastmod' => self::date($r['updated_at']),
            'priority' => '0.5',
        ], $rows);
    }

    private static function alternativeUrls(): array
    {
        // Only software that actually has curated alternatives.
        $rows = Database::all(
            'SELECT DISTINCT s.slug FROM software s
             JOIN software_alternatives a ON a.software_id = s.id
             WHERE s.status = "published"'
        );
        return array_map(fn($r) => [
            'loc' => base_url('/alternatives/' . $r['slug']),
            'priority' => '0.5',
        ], $rows);
    }

    private static function writeUrlset(string $file, array $urls): int
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
            if (!empty($u['lastmod'])) {
                $xml .= '<lastmod>' . $u['lastmod'] . '</lastmod>';
            }
            if (!empty($u['priority'])) {
                $xml .= '<priority>' . $u['priority'] . '</priority>';
            }
            $xml .= "</url>\n";
        }
        $xml .= '</urlset>' . "\n";
        @file_put_contents($file, $xml);
        return count($urls);
    }

    private static function writeIndex(string $file, array $segments): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $now = gmdate('Y-m-d');
        foreach ($segments as $seg) {
            $xml .= '  <sitemap><loc>' . htmlspecialchars(base_url('/sitemaps/' . $seg . '.xml'), ENT_XML1)
                . '</loc><lastmod>' . $now . '</lastmod></sitemap>' . "\n";
        }
        $xml .= '</sitemapindex>' . "\n";
        @file_put_contents($file, $xml);
    }

    private static function date(?string $dt): string
    {
        $ts = $dt ? strtotime($dt) : false;
        return $ts ? gmdate('Y-m-d', $ts) : gmdate('Y-m-d');
    }
}
