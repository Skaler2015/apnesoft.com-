<?php

declare(strict_types=1);

/**
 * Front controller. All web requests route through here.
 * Point your web server document root at this directory.
 *
 * The application core (app/bootstrap.php) may live either:
 *   - as a sibling of this dir      (repo layout: <root>/public + <root>/app)
 *   - in a sibling "softwarehub" dir (Hostinger: public_html + ../softwarehub/app)
 *   - anywhere pointed to by the SOFTWAREHUB_BASE environment variable
 */

$bootstrap = null;
$candidates = [
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../softwarehub/app/bootstrap.php',
    getenv('SOFTWAREHUB_BASE') ? rtrim((string) getenv('SOFTWAREHUB_BASE'), '/') . '/app/bootstrap.php' : null,
];
foreach ($candidates as $candidate) {
    if ($candidate && is_file($candidate)) {
        $bootstrap = $candidate;
        break;
    }
}
if ($bootstrap === null) {
    http_response_code(500);
    exit('Application core not found. Set SOFTWAREHUB_BASE or place the app folder correctly.');
}

require $bootstrap;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

Response::securityHeaders();
Session::start();

$router = new Router();
require \App\Core\Config::get('paths.app') . '/routes.php';

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
