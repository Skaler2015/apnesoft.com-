<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database-backed settings (branding, thresholds, feature toggles).
 * Cached per-request. Falls back to defaults when the DB/table is absent
 * so the site renders during first install.
 */
final class Settings
{
    private static ?array $cache = null;

    private const DEFAULTS = [
        'site_name'        => 'SoftwareHub',
        'tagline'          => 'Find the Right Software for Your PC',
        'logo'             => '',
        'favicon'          => '',
        'primary_color'    => '#4f46e5',
        'secondary_color'  => '#0ea5e9',
        'footer_text'      => 'SoftwareHub — Discover, compare and download trusted software from official sources.',
        'contact_email'    => '',
        'social_twitter'   => '',
        'social_github'    => '',
        // Auto-publish thresholds
        'threshold_auto_publish' => '90',
        'threshold_conditional'  => '70',
        'threshold_review'       => '40',
        // Ads (HTML snippets, admin controlled)
        'ad_header'        => '',
        'ad_incontent'     => '',
        'ad_sidebar'       => '',
        'ad_footer'        => '',
        'ad_software_page' => '',
    ];

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = self::DEFAULTS;
        try {
            $rows = Database::all('SELECT `key`, `value` FROM settings');
            foreach ($rows as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
            // Table not migrated yet — keep defaults.
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::load();
        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function all(): array
    {
        return self::load();
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`, `group`, `type`) VALUES (:k, :v, :g, :t)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['k' => $key, 'v' => is_array($value) ? json_encode($value) : (string) $value, 'g' => $group, 't' => $type]
        );
        self::$cache = null;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return is_numeric($v) ? (int) $v : $default;
    }
}
