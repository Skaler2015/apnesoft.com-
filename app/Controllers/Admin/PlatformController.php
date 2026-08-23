<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\Settings;
use App\Services\Sitemap;

/**
 * Post-login platform chooser. The catalogue is one shared system (one database,
 * one admin) presented as four platform panels — Windows / Mac / iOS / Android.
 * Picking a platform stores it in the session so the software screens scope to
 * it; a change made in any panel is a change to the same shared data.
 */
final class PlatformController extends AdminController
{
    public const PLATFORMS = [
        'windows' => ['Windows', '🪟', '%windows%'],
        'macos'   => ['Mac', '🖥️', '%mac%'],
        'ios'     => ['iOS', '📱', '%ios%'],
        'android' => ['Android', '🤖', '%android%'],
    ];

    /** GET /admin/platform — the chooser shown right after login. */
    public function chooser(array $args = []): never
    {
        $this->requirePermission('software.view');
        $cards = [];
        foreach (self::PLATFORMS as $slug => [$label, $icon, $like]) {
            $cards[$slug] = [
                'label' => $label,
                'icon'  => $icon,
                'count' => (int) Database::scalar(
                    'SELECT COUNT(*) FROM software WHERE operating_system LIKE :l', ['l' => $like]
                ),
            ];
        }
        $this->render('admin/platform', ['title' => 'Choose a platform', 'cards' => $cards]);
    }

    /** GET /admin/platform/{slug} — open that platform's panel. */
    public function select(array $args): never
    {
        $this->requirePermission('software.view');
        $slug = (string) ($args['slug'] ?? '');
        if (isset(self::PLATFORMS[$slug])) {
            Session::set('admin_platform', $slug);
        }
        $this->redirect(base_url('/admin/software'));
    }

    /** Current platform label + slug from the session (null when none chosen). */
    public static function current(): ?array
    {
        $slug = (string) Session::get('admin_platform', '');
        if (!isset(self::PLATFORMS[$slug])) {
            return null;
        }
        return ['slug' => $slug, 'label' => self::PLATFORMS[$slug][0]];
    }

    /** POST /admin/platform/reset — delete ALL software for a clean slate. */
    public function wipe(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);

        // Remove every software row; FK cascades clear versions / OS links /
        // features / screenshots. Clear the loosely-linked tables too.
        Database::run('DELETE FROM software');
        foreach ([
            'DELETE FROM seo_metadata WHERE entity_type = "software"',
            'DELETE FROM update_history',
            'DELETE FROM duplicate_candidates',
        ] as $sql) {
            try { Database::run($sql); } catch (\Throwable $e) {}
        }

        // Reset every import cursor and pause auto-import so it stays empty until
        // the admin decides — they can re-enable it from Bulk Import.
        foreach (['bulk_done', 'bulk_qi', 'bulk_page', 'disc_qi', 'disc_page',
                  'choco_off', 'winget_page', 'brew_off', 'flathub_off', 'fdroid_off', 'catalog_turn'] as $k) {
            Settings::set($k, '0', 'bulk', 'int');
        }
        Settings::set('auto_discovery', '0', 'bulk');
        Settings::set('ai_enabled', '0', 'ai');

        $this->audit('software.wipe_all');
        try { Sitemap::generateAll(); } catch (\Throwable $e) {}

        Session::flash('ok', 'All software deleted and auto-import paused. Start fresh — pick a platform and add software.');
        $this->redirect(base_url('/admin/platform'));
    }
}
