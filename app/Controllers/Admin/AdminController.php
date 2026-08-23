<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;

abstract class AdminController extends Controller
{
    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        // The admin panel manages every platform, so it is never OS-filtered.
        \App\Models\Software::setPlatform(null);
    }

    protected function render(string $view, array $data = []): never
    {
        $data['_admin'] = Auth::user();
        $data['_unread'] = \App\Models\Notification::unreadCount();
        Response::html(View::render($view, $data, 'admin/layout'));
    }

    protected function requirePermission(string $perm): void
    {
        Auth::requirePermission($perm);
    }

    protected function audit(string $action, string $entity = null, int $entityId = null, string $detail = ''): void
    {
        try {
            $user = Auth::user();
            Database::insert('admin_logs', [
                'admin_id'  => $user['id'] ?? null,
                'action'    => $action,
                'entity'    => $entity,
                'entity_id' => $entityId,
                'detail'    => $detail,
                'ip'        => $this->request->ip(),
            ]);
        } catch (\Throwable $e) {
        }
    }
}
