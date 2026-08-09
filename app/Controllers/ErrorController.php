<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\View;

final class ErrorController extends Controller
{
    public function notFound(array $args = []): never
    {
        Response::html(View::render('errors/404', [
            'title'   => 'Page Not Found',
            'noindex' => true,
        ]), 404);
    }
}
