<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Services\BulkImport;

/**
 * Browser-driven bulk importer. The page imports one batch per load and
 * auto-refreshes to continue until the target is reached — no cron or CLI
 * needed, and each request stays within PHP execution + API rate limits.
 */
final class BulkImportController extends AdminController
{
    public function index(array $args = []): never
    {
        $this->requirePermission('software.manage');

        $running = $this->request->query('run') === '1';
        $result = null;

        if ($running) {
            $result = BulkImport::runBatch();
        }

        $progress = BulkImport::progress();

        $this->render('admin/bulk_import', [
            'title'    => 'Bulk Import',
            'running'  => $running,
            'result'   => $result,
            'progress' => $progress,
            'target'   => $progress['target'],
        ]);
    }

    /** POST /admin/bulk-import/start — reset the cursor and begin. */
    public function start(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $target = max(1, min(10000, $this->request->int('target', 2000)));
        BulkImport::reset($target);
        $this->audit('bulk_import.start', null, null, 'target ' . $target);
        $this->redirect(base_url('/admin/bulk-import?run=1'));
    }
}
