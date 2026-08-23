<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Per-hostname platform context. One codebase + one database + one admin serve
 * four platform sites; the public pages filter their software by the OS that
 * matches the current subdomain:
 *
 *   apnesoft.com          -> Windows
 *   mac.apnesoft.com      -> Mac
 *   ios.apnesoft.com      -> iOS
 *   android.apnesoft.com  -> Android
 *   (linux.apnesoft.com   -> Linux, if ever added)
 *
 * On the CLI (cron) there is no host, so os() returns null and nothing is
 * filtered — the automation processes every platform.
 */
final class SiteContext
{
    private const SUB = [
        'mac' => 'macos', 'ios' => 'ios', 'android' => 'android',
        'linux' => 'linux', 'windows' => 'windows',
    ];
    private const LABELS = [
        'windows' => 'Windows', 'macos' => 'Mac', 'ios' => 'iOS',
        'android' => 'Android', 'linux' => 'Linux',
    ];

    /** Platform switcher shown in the header (slug => label). */
    public static function platforms(): array
    {
        return ['windows' => 'Windows', 'macos' => 'Mac', 'ios' => 'iOS', 'android' => 'Android'];
    }

    /** Current platform OS slug, or null on CLI / unknown host (no filtering). */
    public static function os(): ?string
    {
        $host = self::host();
        if ($host === '') {
            return null; // CLI / cron — process everything
        }
        $sub = explode('.', $host)[0] ?? '';
        if (isset(self::SUB[$sub])) {
            return self::SUB[$sub];
        }
        // Bare apex or www -> the main Windows site.
        return 'windows';
    }

    public static function label(?string $os = null): string
    {
        $os = $os ?? self::os();
        return self::LABELS[$os] ?? '';
    }

    /** The registrable apex host (strips a known platform sub-label / www). */
    public static function apexHost(): string
    {
        $host = self::host();
        if ($host === '') {
            $host = preg_replace('#^https?://#', '', (string) Config::get('app.url', 'apnesoft.com')) ?? 'apnesoft.com';
            $host = rtrim($host, '/');
        }
        $labels = explode('.', $host);
        if (count($labels) >= 3 && in_array($labels[0], array_merge(array_keys(self::SUB), ['www']), true)) {
            array_shift($labels);
        }
        return implode('.', $labels) ?: $host;
    }

    /** Absolute URL for a platform's site (used by the header switcher). */
    public static function platformUrl(string $os): string
    {
        $apex = self::apexHost();
        return match ($os) {
            'macos'   => 'https://mac.' . $apex . '/',
            'ios'     => 'https://ios.' . $apex . '/',
            'android' => 'https://android.' . $apex . '/',
            'linux'   => 'https://linux.' . $apex . '/',
            default   => 'https://' . $apex . '/',
        };
    }

    private static function host(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host === '' ? '' : (preg_replace('/:\d+$/', '', $host) ?? '');
    }
}
