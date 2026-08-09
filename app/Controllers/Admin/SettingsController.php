<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Settings;

final class SettingsController extends AdminController
{
    private const KEYS = [
        'site_name', 'tagline', 'logo', 'favicon', 'primary_color', 'secondary_color',
        'footer_text', 'contact_email', 'social_twitter', 'social_github',
        'threshold_auto_publish', 'threshold_conditional', 'threshold_review',
        'ad_header', 'ad_incontent', 'ad_sidebar', 'ad_footer', 'ad_software_page',
    ];

    public function index(array $args = []): never
    {
        Auth::requireLogin();
        $this->render('admin/settings', [
            'title'    => 'Settings',
            'settings' => Settings::all(),
        ]);
    }

    public function save(array $args = []): never
    {
        $this->requirePermission('seo.manage'); // super_admin has *, seo_manager allowed for branding/seo
        Csrf::check($this->request);
        foreach (self::KEYS as $key) {
            if ($this->request->input($key) !== null) {
                Settings::set($key, (string) $this->request->input($key), 'general');
            }
        }
        $this->audit('settings.update');
        Session::flash('ok', 'Settings saved.');
        $this->redirect(base_url('/admin/settings'));
    }
}
