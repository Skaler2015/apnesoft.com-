<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * Generates factual SEO metadata + schema.org structured data from DB fields.
 * Never invents facts; only emits properties backed by real data.
 */
final class Seo
{
    public static function generateForSoftware(int $softwareId): void
    {
        $s = Database::first('SELECT * FROM software WHERE id = :id', ['id' => $softwareId]);
        if (!$s) {
            return;
        }

        $site = Settings::get('site_name', 'SoftwareHub');
        $osLabel = $s['operating_system'] ? ' for ' . $s['operating_system'] : '';
        $title = trim("{$s['name']}{$osLabel} — Download" . ($s['version'] ? " {$s['version']}" : ''));
        $title = mb_substr($title, 0, 65) . ' | ' . $site;

        $descParts = array_filter([
            $s['short_description'] ?: str_excerpt($s['long_description'], 150),
            $s['developer_name'] ? 'By ' . $s['developer_name'] . '.' : '',
            $s['license_type'] ? $s['license_type'] . '.' : '',
        ]);
        $desc = mb_substr(trim(implode(' ', $descParts)), 0, 300);

        $canonical = base_url('/software/' . $s['slug']);
        $indexable = $s['status'] === 'published' ? 1 : 0;

        $structured = self::softwareSchema($s, $canonical);

        Database::run(
            'INSERT INTO seo_metadata (entity_type, entity_id, title, description, canonical, og_title, og_description, og_image, is_indexable, structured)
             VALUES ("software", :id, :t, :d, :c, :ot, :od, :img, :idx, :st)
             ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), canonical=VALUES(canonical),
                 og_title=VALUES(og_title), og_description=VALUES(og_description), og_image=VALUES(og_image),
                 is_indexable=VALUES(is_indexable), structured=VALUES(structured)',
            [
                'id'  => $softwareId,
                't'   => $title,
                'd'   => $desc,
                'c'   => $canonical,
                'ot'  => $s['name'] . $osLabel,
                'od'  => $desc,
                'img' => $s['logo'] ?: '',
                'idx' => $indexable,
                'st'  => json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /** Build a SoftwareApplication schema object with only available properties. */
    public static function softwareSchema(array $s, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'SoftwareApplication',
            'name'     => $s['name'],
            'url'      => $url,
        ];
        if (!empty($s['long_description']) || !empty($s['short_description'])) {
            $schema['description'] = str_excerpt($s['long_description'] ?: $s['short_description'], 300);
        }
        if (!empty($s['version'])) {
            $schema['softwareVersion'] = $s['version'];
        }
        if (!empty($s['operating_system'])) {
            $schema['operatingSystem'] = $s['operating_system'];
        }
        if (!empty($s['file_size'])) {
            $schema['fileSize'] = $s['file_size'];
        }
        if (!empty($s['developer_name'])) {
            $schema['author'] = ['@type' => 'Organization', 'name' => $s['developer_name']];
        }
        if (!empty($s['release_date'])) {
            $schema['datePublished'] = $s['release_date'];
        }
        if (!empty($s['logo'])) {
            $schema['image'] = $s['logo'];
        }
        // Category maps loosely to applicationCategory.
        if (!empty($s['category_id'])) {
            $cat = Database::scalar('SELECT name FROM categories WHERE id = :id', ['id' => $s['category_id']]);
            if ($cat) {
                $schema['applicationCategory'] = $cat;
            }
        }
        // Price only when we actually know it's free.
        if (in_array($s['price_type'] ?? '', ['free', 'open_source'], true)) {
            $schema['offers'] = ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'];
        }
        return $schema;
    }

    public static function breadcrumb(array $items): array
    {
        $list = [];
        foreach ($items as $i => $item) {
            $list[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    public static function forEntity(string $type, int $id): ?array
    {
        return Database::first(
            'SELECT * FROM seo_metadata WHERE entity_type = :t AND entity_id = :id',
            ['t' => $type, 'id' => $id]
        );
    }
}
