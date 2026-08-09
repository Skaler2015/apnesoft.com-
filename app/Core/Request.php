<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish request wrapper around PHP superglobals with light sanitising.
 */
final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $post;
    public array $server;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->server = $_SERVER;
        $this->query  = $_GET;
        $this->post   = $_POST;

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->path = '/' . trim(rawurldecode($path), '/');
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_string($v) ? trim($v) : $default;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function device(): string
    {
        $ua = strtolower($this->userAgent());
        if (preg_match('/ipad|tablet|playbook|silk/', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobi|android|iphone|ipod|windows phone/', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }
}
