<?php
/**
 * Loads environment (.env) and returns the application configuration array.
 * Falls back to sane defaults so the app can boot for a first-time installer.
 */

$root = dirname(__DIR__);

// Minimal .env parser (no external dependency for shared hosting).
$env = [];
$envFile = $root . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // Strip surrounding quotes.
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v) - 1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $env[$k] = $v;
    }
}

$get = static function (string $key, $default = null) use ($env) {
    if (array_key_exists($key, $env)) {
        return $env[$key];
    }
    $server = getenv($key);
    return $server !== false ? $server : $default;
};

$bool = static fn($v) => in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);

return [
    'app' => [
        'name'   => $get('APP_NAME', 'SoftwareHub'),
        'env'    => $get('APP_ENV', 'production'),
        'debug'  => $bool($get('APP_DEBUG', 'false')),
        'url'    => rtrim((string) $get('APP_URL', 'http://localhost'), '/'),
        'key'    => $get('APP_KEY', 'insecure-development-key-change-me'),
        'root'   => $root,
    ],
    'db' => [
        'host'    => $get('DB_HOST', '127.0.0.1'),
        'port'    => (int) $get('DB_PORT', 3306),
        'name'    => $get('DB_NAME', 'softwarehub'),
        'user'    => $get('DB_USER', 'root'),
        'pass'    => $get('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'integrations' => [
        'github_token'          => $get('GITHUB_TOKEN', ''),
        'github_webhook_secret' => $get('GITHUB_WEBHOOK_SECRET', ''),
    ],
    'automation' => [
        'enabled'    => $bool($get('CRON_ENABLED', 'true')),
        'user_agent' => $get('HTTP_USER_AGENT', 'SoftwareHubBot/1.0'),
    ],
    'paths' => [
        'root'    => $root,
        'app'     => $root . '/app',
        'views'   => $root . '/app/Views',
        'storage' => $root . '/storage',
        // On shared hosting the web root ("public_html") is separate from the app
        // folder. Set APP_PUBLIC_DIR in .env to that absolute path; otherwise the
        // repo's own /public directory is used.
        'public'  => rtrim((string) $get('APP_PUBLIC_DIR', $root . '/public'), '/'),
    ],
];
