<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal regex router. Routes map "GET /software/{slug}" to [Controller, method].
 * {param} captures a single path segment; {param:.*} captures the rest.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, params:string[], handler:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler): void
    {
        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)(?::(.+?))?\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '(' . ($m[2] ?? '[^/]+') . ')';
        }, $pattern);

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    public function get(string $p, array $h): void { $this->add('GET', $p, $h); }
    public function post(string $p, array $h): void { $this->add('POST', $p, $h); }

    public function dispatch(Request $request): void
    {
        $matchedPathButNotMethod = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $matchedPathButNotMethod = true;
                continue;
            }

            array_shift($m);
            $args = [];
            foreach ($route['params'] as $i => $name) {
                $args[$name] = $m[$i] ?? null;
            }

            [$class, $action] = $route['handler'];
            $controller = new $class($request);
            $controller->$action($args);
            return;
        }

        if ($matchedPathButNotMethod) {
            Response::html('Method Not Allowed', 405);
        }

        // 404
        (new \App\Controllers\ErrorController($request))->notFound();
    }
}
