<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Settings;
use App\Services\BulkImport;
use App\Services\CatalogImport;
use App\Services\Dedupe;

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
            'title'         => 'Bulk Import',
            'running'       => $running,
            'result'        => $result,
            'progress'      => $progress,
            'target'        => $progress['target'],
            'autoDiscovery' => (string) Settings::get('auto_discovery', '1') !== '0',
            'total'         => (int) \App\Core\Database::scalar('SELECT COUNT(*) FROM software WHERE status = "published"'),
            'dupeGroups'    => Dedupe::duplicateGroups(),
        ]);
    }

    /** POST /admin/bulk-import/dedupe — merge & remove already-published duplicates. */
    public function dedupe(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $r = Dedupe::deduplicateExisting(400);
        $this->audit('software.dedupe', null, null, 'removed ' . $r['removed']);
        Session::flash('ok', "Removed {$r['removed']} duplicate entries. "
            . ($r['remaining'] > 0 ? "{$r['remaining']} duplicate groups remain — click again to continue." : 'No duplicates left. 🎉'));
        $this->redirect(base_url('/admin/bulk-import'));
    }

    /** POST /admin/bulk-import/catalog — import one batch from a platform catalog. */
    public function catalog(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $source = $this->request->str('source');
        if (!in_array($source, CatalogImport::sources(), true)) {
            $this->redirect(base_url('/admin/bulk-import'));
        }
        $r = CatalogImport::run($source, 150);
        $label = [
            'popular' => 'Popular apps', 'chocolatey' => 'Windows (Chocolatey)',
            'homebrew' => 'macOS (Homebrew)', 'flathub' => 'Linux (Flathub)', 'fdroid' => 'Android (F-Droid)',
        ][$source] ?? $source;
        Session::flash('ok', "$label: added {$r['created']} new, {$r['skipped']} skipped. " . ($r['message'] ?? ''));
        $this->redirect(base_url('/admin/bulk-import'));
    }

    /** POST /admin/bulk-import/catalog-all — import a batch from every catalog at once. */
    public function catalogAll(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $r = CatalogImport::runAll(200);
        $this->audit('bulk_import.catalog_all', null, null, 'created ' . $r['created']);
        Session::flash('ok', "All catalogs: added {$r['created']} new "
            . '(popular ' . ($r['per']['popular'] ?? 0) . ', Windows ' . ($r['per']['chocolatey'] ?? 0)
            . ', macOS ' . ($r['per']['homebrew'] ?? 0) . ', Linux ' . ($r['per']['flathub'] ?? 0)
            . ', Android ' . ($r['per']['fdroid'] ?? 0) . '). Run again for more.');
        $this->redirect(base_url('/admin/bulk-import'));
    }

    /** POST /admin/bulk-import/toggle — turn hourly auto-discovery on/off. */
    public function toggle(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $on = $this->request->str('enable') === '1';
        Settings::set('auto_discovery', $on ? '1' : '0', 'bulk');
        $this->audit('auto_discovery.' . ($on ? 'on' : 'off'));
        \App\Core\Session::flash('ok', 'Automatic hourly discovery ' . ($on ? 'enabled' : 'disabled') . '.');
        $this->redirect(base_url('/admin/bulk-import'));
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
