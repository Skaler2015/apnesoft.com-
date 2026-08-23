<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Settings;

if (!function_exists('e')) {
    /** HTML-escape a value for safe output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        // Build from the current host so each platform subdomain keeps its own
        // links (mac.apnesoft.com stays on mac.apnesoft.com). Falls back to the
        // configured APP_URL on the CLI (cron/sitemap), where there is no host.
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
            $base = ($https ? 'https' : 'http') . '://' . $host;
        } else {
            $base = rtrim((string) Config::get('app.url', ''), '/');
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        // Root-relative so CSS/JS/images always load from the current origin and
        // scheme — avoids any host/scheme mismatch across the platform subdomains
        // (mac./ios./android.) that would otherwise block them as mixed content.
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-') ?: 'item';
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('str_excerpt')) {
    function str_excerpt(?string $text, int $length = 160): string
    {
        $text = trim(strip_tags((string) $text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        if (!$datetime) {
            return 'unknown';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return 'unknown';
        }
        $diff = time() - $ts;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' h ago';
        if ($diff < 2592000) return floor($diff / 86400) . ' d ago';
        if ($diff < 31536000) return floor($diff / 2592000) . ' mo ago';
        return floor($diff / 31536000) . ' y ago';
    }
}

if (!function_exists('trust_badge')) {
    /** Map a trust score to a label + color class. */
    function trust_badge(int $score): array
    {
        if ($score >= 70) {
            return ['label' => 'Highly Verified', 'class' => 'trust-green', 'dot' => '🟢'];
        }
        if ($score >= 40) {
            return ['label' => 'Needs Review', 'class' => 'trust-yellow', 'dot' => '🟡'];
        }
        return ['label' => 'Unverified', 'class' => 'trust-red', 'dot' => '🔴'];
    }
}

if (!function_exists('quality_label')) {
    function quality_label(int $score): string
    {
        if ($score >= 80) return 'Excellent';
        if ($score >= 55) return 'Good';
        return 'Needs Review';
    }
}

if (!function_exists('license_badge')) {
    /**
     * Directory-style licence tag (FREE / OPEN SOURCE / TRIAL …) from real data.
     * Returns ['label' => '', 'class' => ''] when the licence is unknown so we
     * never guess. @param array $item a software row
     */
    function license_badge(array $item): array
    {
        $price = strtolower((string) ($item['price_type'] ?? ''));
        $oss   = !empty($item['is_open_source']);

        if ($price === 'open_source' || ($oss && in_array($price, ['', 'free'], true))) {
            return ['label' => 'OPEN SOURCE', 'class' => 'lb-oss'];
        }
        return match ($price) {
            'free'     => ['label' => 'FREE', 'class' => 'lb-free'],
            'freemium' => ['label' => 'FREEMIUM', 'class' => 'lb-freemium'],
            'trial'    => ['label' => 'TRIAL', 'class' => 'lb-trial'],
            'demo'     => ['label' => 'DEMO', 'class' => 'lb-demo'],
            'paid'     => ['label' => 'PAID', 'class' => 'lb-paid'],
            default    => ['label' => '', 'class' => ''],
        };
    }
}

if (!function_exists('download_label')) {
    /** OS-specific download button label, e.g. "Download for Windows". */
    function download_label(array $item): string
    {
        $os = strtolower((string) ($item['operating_system'] ?? ''));
        foreach (['windows' => 'Windows', 'macos' => 'macOS', 'mac' => 'macOS',
                  'android' => 'Android', 'linux' => 'Linux', 'ios' => 'iOS'] as $needle => $label) {
            if (str_contains($os, $needle)) {
                return 'Download for ' . $label;
            }
        }
        return 'Download';
    }
}

if (!function_exists('sanitize_rich')) {
    /** Keep only a safe subset of HTML (for admin-entered rich descriptions). */
    function sanitize_rich(string $s): string
    {
        $s = strip_tags($s, '<p><br><b><strong><i><em><u><ul><ol><li><h3><h4><a>');
        $s = preg_replace('~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $s) ?? $s; // drop on* handlers
        $s = preg_replace('~(href\s*=\s*["\']?)\s*(?:javascript|data|vbscript):~i', '$1#', $s) ?? $s;
        return trim($s);
    }
}

if (!function_exists('render_desc')) {
    /** Render a long description: safe HTML if it has tags, else escaped text. */
    function render_desc(?string $s): string
    {
        $s = (string) $s;
        return str_contains($s, '<') ? sanitize_rich($s) : nl2br(e($s));
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        return e($_POST[$key] ?? $default);
    }
}
