<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain PHP template renderer with a single layout wrapper.
 * Views live in app/Views. A view can set $title/$meta via the passed data.
 */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** Render a view inside the given layout and return HTML. */
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): string
    {
        $content = self::partial($view, $data);
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    /** Render a view fragment without a layout. */
    public static function partial(string $view, array $data = []): string
    {
        $file = Config::get('paths.views') . '/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view");
        }
        extract(array_merge(self::$shared, $data), EXTR_OVERWRITE);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
