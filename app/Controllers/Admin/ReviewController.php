<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;

/**
 * Review queue for software pending review + duplicate candidates.
 */
final class ReviewController extends AdminController
{
    public function index(array $args = []): never
    {
        $this->requirePermission('software.review');
        $this->render('admin/review/index', [
            'title'      => 'Review Queue',
            'pending'    => Database::all('SELECT * FROM software WHERE status = "review" ORDER BY created_at DESC LIMIT 100'),
            'duplicates' => Database::all(
                'SELECT d.*, s.name AS software_name, m.name AS match_name
                 FROM duplicate_candidates d
                 JOIN software s ON s.id = d.software_id
                 JOIN software m ON m.id = d.match_id
                 WHERE d.status = "pending" ORDER BY d.confidence DESC LIMIT 100'
            ),
        ]);
    }

    public function resolveDuplicate(array $args): never
    {
        $this->requirePermission('duplicate.review');
        Csrf::check($this->request);
        $id = (int) ($args['id'] ?? 0);
        $decision = $this->request->str('decision'); // merge|ignore
        Database::update('duplicate_candidates', ['status' => $decision === 'merge' ? 'merged' : 'ignored'], ['id' => $id]);
        $this->audit('duplicate.' . $decision, 'duplicate', $id);
        Session::flash('ok', 'Duplicate ' . $decision . 'd.');
        $this->redirect(base_url('/admin/review'));
    }
}
