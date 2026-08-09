<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Static config accessor with dot-notation lookup.
 * Config is immutable once loaded from config/config.php.
 */
final class Config
{
    private static array $items = [];

    public static function load(array $items): void
    {
        self::$items = $items;
    }

    /** Fetch a config value using "section.key" dot notation. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
