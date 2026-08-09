<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function view(string $view, array $data = [], string $layout = 'layouts/app'): never
    {
        Response::html(View::render($view, $data, $layout));
    }

    protected function json(array $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $to): never
    {
        Response::redirect($to);
    }

    /** Record a privacy-conscious page view (no IP / PII). */
    protected function trackView(string $path, ?string $entityType = null, ?int $entityId = null): void
    {
        try {
            Database::insert('page_views', [
                'path'        => mb_substr($path, 0, 300),
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'device'      => $this->request->device(),
            ]);
        } catch (\Throwable $e) {
            // analytics is best-effort
        }
    }

    protected function page(int $default = 1): int
    {
        return max(1, $this->request->int('page', $default));
    }
}
