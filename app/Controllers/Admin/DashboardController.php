<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Notification;

final class DashboardController extends AdminController
{
    public function index(array $args = []): never
    {
        Auth::requireLogin();

        $cards = [
            'total_software' => (int) Database::scalar('SELECT COUNT(*) FROM software'),
            'published'      => (int) Database::scalar('SELECT COUNT(*) FROM software WHERE status = "published"'),
            'new_today'      => (int) Database::scalar('SELECT COUNT(*) FROM software WHERE DATE(created_at) = CURDATE()'),
            'updated_today'  => (int) Database::scalar('SELECT COUNT(*) FROM update_history WHERE DATE(created_at) = CURDATE()'),
            'pending_review' => (int) Database::scalar('SELECT COUNT(*) FROM software WHERE status = "review"'),
            'rejected'       => (int) Database::scalar('SELECT COUNT(*) FROM software WHERE status = "rejected"'),
            'broken_links'   => (int) Database::scalar('SELECT COUNT(DISTINCT software_id) FROM verification_logs WHERE result = "broken" AND created_at > (NOW() - INTERVAL 7 DAY)'),
            'failed_crawls'  => (int) Database::scalar('SELECT COUNT(*) FROM crawler_logs WHERE status = "error" AND created_at > (NOW() - INTERVAL 7 DAY)'),
            'active_sources' => (int) Database::scalar('SELECT COUNT(*) FROM software_sources WHERE status = "active"'),
        ];

        // Growth: software added per day (last 14 days).
        $growth = Database::all(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM software
             WHERE created_at > (NOW() - INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY d'
        );
        $updatesPerDay = Database::all(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM update_history
             WHERE created_at > (NOW() - INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY d'
        );

        $this->render('admin/dashboard', [
            'title'         => 'Dashboard',
            'cards'         => $cards,
            'growth'        => $growth,
            'updatesPerDay' => $updatesPerDay,
            'topViewed'     => Database::all('SELECT name, slug, views FROM software ORDER BY views DESC LIMIT 8'),
            'topDownloads'  => Database::all('SELECT name, slug, download_clicks FROM software ORDER BY download_clicks DESC LIMIT 8'),
            'notifications' => Notification::recent(8),
            'jobs'          => Database::all('SELECT * FROM cron_jobs ORDER BY name'),
        ]);
    }

    public function notifications(array $args = []): never
    {
        Auth::requireLogin();
        Notification::markAllRead();
        $this->render('admin/notifications', [
            'title'         => 'Notifications',
            'notifications' => Notification::recent(100),
        ]);
    }
}
