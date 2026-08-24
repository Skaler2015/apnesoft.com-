<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * SSRF guard for outbound fetches of user-supplied URLs (import, link check,
 * version/size fetch, discovery). Rejects non-http(s) schemes and any host
 * that resolves to a private, reserved, loopback, link-local or cloud
 * metadata address — so an admin (or an attacker via a crafted URL) cannot
 * make the server reach internal services.
 *
 * Usage:  if (($why = SafeUrl::reject($url)) !== null) { deny($why); }
 */
final class SafeUrl
{
    public static function isSafe(string $url): bool
    {
        return self::reject($url) === null;
    }

    /** Returns a human reason to reject the URL, or null when it is safe. */
    public static function reject(string $url): ?string
    {
        $url = trim($url);
        $p = parse_url($url);
        if ($p === false || empty($p['scheme']) || !in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
            return 'Only http/https links are allowed.';
        }
        $host = strtolower((string) ($p['host'] ?? ''));
        if ($host === '') {
            return 'The link has no host.';
        }
        if (in_array($host, ['localhost', 'localhost.localdomain', 'ip6-localhost'], true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            return 'Local/internal addresses are blocked.';
        }
        $ips = self::resolve($host);
        if (!$ips) {
            return 'The link\'s host could not be resolved.';
        }
        foreach ($ips as $ip) {
            if (!self::ipIsPublic($ip)) {
                return 'That link points to a private/internal address and was blocked.';
            }
        }
        return null;
    }

    /** @return string[] resolved IPs (or the literal IP host). */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }
        if (function_exists('dns_get_record')) {
            $rec = @dns_get_record($host, DNS_AAAA);
            if (is_array($rec)) {
                foreach ($rec as $r) {
                    if (!empty($r['ipv6'])) {
                        $ips[] = $r['ipv6'];
                    }
                }
            }
        }
        return array_values(array_unique($ips));
    }

    private static function ipIsPublic(string $ip): bool
    {
        // IPv4: reject private + reserved (covers 10/8, 172.16/12, 192.168/16,
        // 127/8, 169.254/16 link-local incl. 169.254.169.254 metadata, 0/8, …).
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                && $ip !== '169.254.169.254';
        }
        // IPv6: reject private + reserved, plus explicit loopback/ULA/link-local.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            $l = strtolower($ip);
            if ($l === '::1' || $l === '::' || str_starts_with($l, 'fc') || str_starts_with($l, 'fd')
                || str_starts_with($l, 'fe8') || str_starts_with($l, 'fe9') || str_starts_with($l, 'fea') || str_starts_with($l, 'feb')
                || str_starts_with($l, '::ffff:')) {
                return false;
            }
            return true;
        }
        return false;
    }
}
