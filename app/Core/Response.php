<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response helpers. Sends security headers by default.
 */
final class Response
{
    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // A conservative CSP; assets are self-hosted.
        header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; "
            . "style-src 'self' 'unsafe-inline'; script-src 'self'; "
            . "connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    }

    public static function html(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $to);
        exit;
    }

    public static function notFound(string $body = 'Not Found'): never
    {
        self::html($body, 404);
    }
}
