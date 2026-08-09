<?php

declare(strict_types=1);

/**
 * Front controller. All web requests route through here.
 * Point your web server document root at /public.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

Response::securityHeaders();
Session::start();

$router = new Router();
require dirname(__DIR__) . '/app/routes.php';

$request = new Request();

try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    \App\Core\Logger::error('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (\App\Core\Config::get('app.debug')) {
        Response::html('<pre>' . htmlspecialchars((string) $e) . '</pre>', 500);
    }
    http_response_code(500);
    try {
        echo \App\Core\View::render('errors/500', ['title' => 'Server Error', 'noindex' => true]);
    } catch (\Throwable $inner) {
        echo 'Internal Server Error';
    }
}
