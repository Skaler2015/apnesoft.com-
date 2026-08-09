<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Services\DiscoveryEngine;
use App\Services\JobRunner;
use App\Services\LinkChecker;
use App\Services\Seo;
use App\Services\Sitemap;

/**
 * Automation Center — view + trigger cron jobs from the UI.
 */
final class AutomationController extends AdminController
{
    public function index(array $args = []): never
    {
        $this->requirePermission('automation.manage');
        $this->render('admin/automation/index', [
            'title' => 'Automation Center',
            'jobs'  => Database::all('SELECT * FROM cron_jobs ORDER BY name'),
            'logs'  => Database::all('SELECT * FROM crawler_logs ORDER BY created_at DESC LIMIT 20'),
        ]);
    }

    /** POST /admin/automation/run */
    public function run(array $args = []): never
    {
        $this->requirePermission('cron.run');
        Csrf::check($this->request);
        $key = $this->request->str('key');

        $result = match ($key) {
            'discover'      => JobRunner::run('discover', 'Discovery', fn() => DiscoveryEngine::runAll()),
            'github_sync'   => JobRunner::run('github_sync', 'GitHub Sync', fn() => DiscoveryEngine::runAll('github_api')),
            'winget_sync'   => JobRunner::run('winget_sync', 'Winget Sync', fn() => DiscoveryEngine::runAll('winget')),
            'rss_sync'      => JobRunner::run('rss_sync', 'RSS Sync', fn() => DiscoveryEngine::runAll('rss')),
            'link_check'    => JobRunner::run('link_check', 'Link Checker', fn() => LinkChecker::run(40)),
            'sitemap'       => JobRunner::run('sitemap', 'Sitemap', fn() => ['processed' => array_sum(Sitemap::generateAll())]),
            'seo_update'    => JobRunner::run('seo_update', 'SEO Generator', fn() => $this->rebuildSeo()),
            default         => ['status' => 'unknown', 'message' => 'Unknown job'],
        };

        Session::flash('ok', "Job '$key' finished: " . ($result['status'] ?? 'done')
            . (isset($result['processed']) ? " (processed {$result['processed']})" : ''));
        $this->redirect(base_url('/admin/automation'));
    }

    /** POST /admin/automation/toggle */
    public function toggle(array $args = []): never
    {
        $this->requirePermission('automation.manage');
        Csrf::check($this->request);
        $key = $this->request->str('key');
        Database::run('UPDATE cron_jobs SET enabled = 1 - enabled WHERE `key` = :k', ['k' => $key]);
        $this->redirect(base_url('/admin/automation'));
    }

    private function rebuildSeo(): array
    {
        $ids = Database::all('SELECT id FROM software WHERE status = "published"');
        foreach ($ids as $row) {
            Seo::generateForSoftware((int) $row['id']);
        }
        return ['processed' => count($ids)];
    }
}
