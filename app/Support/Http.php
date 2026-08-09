<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

/**
 * Small cURL-based HTTP client for the discovery engine and link checker.
 * Sends a descriptive bot user-agent and enforces timeouts. Read-only GET/HEAD.
 */
final class Http
{
    public static function get(string $url, array $headers = [], int $timeout = 15): array
    {
        return self::request('GET', $url, $headers, $timeout);
    }

    public static function head(string $url, array $headers = [], int $timeout = 12): array
    {
        return self::request('HEAD', $url, $headers, $timeout);
    }

    /**
     * @return array{status:int, body:string, effective_url:string, error:?string, headers:array}
     */
    public static function request(string $method, string $url, array $headers = [], int $timeout = 15): array
    {
        $ua = (string) Config::get('automation.user_agent', 'SoftwareHubBot/1.0');

        if (!function_exists('curl_init')) {
            return self::fallback($method, $url, $ua, $timeout);
        }

        $ch = curl_init();
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_NOBODY         => $method === 'HEAD',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => array_merge(['Accept: */*'], $headers),
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        $body = curl_exec($ch);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return [
            'status'        => $status,
            'body'          => is_string($body) ? $body : '',
            'effective_url' => $effective ?: $url,
            'error'         => $error,
            'headers'       => $respHeaders,
        ];
    }

    private static function fallback(string $method, string $url, string $ua, int $timeout): array
    {
        $ctx = stream_context_create([
            'http' => ['method' => $method, 'timeout' => $timeout, 'header' => "User-Agent: $ua\r\n", 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        return [
            'status'        => $status,
            'body'          => is_string($body) ? $body : '',
            'effective_url' => $url,
            'error'         => $body === false ? 'request failed' : null,
            'headers'       => [],
        ];
    }
}
