<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Config;
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
        'ai_model', 'ai_enabled',
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

        // AI enable is a checkbox — absent means off.
        Settings::set('ai_enabled', $this->request->str('ai_enabled') === '1' ? '1' : '0', 'ai');

        // Publishing automation.
        Settings::set('auto_screenshot', $this->request->str('auto_screenshot') === '1' ? '1' : '0', 'bulk');
        Settings::set('daily_publish', (string) max(0, min(100, $this->request->int('daily_publish'))), 'bulk');

        // Anthropic API key: stored encrypted. Only overwrite when a new key is
        // typed; the form shows a masked placeholder, never the real key.
        $newKey = trim((string) $this->request->input('ai_api_key', ''));
        if ($newKey !== '' && !str_starts_with($newKey, '••')) {
            Settings::set('ai_api_key_enc', \App\Core\Crypto::encrypt($newKey), 'ai');
        } elseif ($this->request->str('ai_api_key_clear') === '1') {
            Settings::set('ai_api_key_enc', '', 'ai');
        }

        // Uploaded logo / favicon override the URL fields.
        $uploadError = false;
        foreach (['logo' => 'logo_file', 'favicon' => 'favicon_file'] as $settingKey => $inputName) {
            if (!empty($_FILES[$inputName]['tmp_name']) && is_uploaded_file($_FILES[$inputName]['tmp_name'])) {
                $url = $this->handleUpload($_FILES[$inputName]);
                if ($url !== null) {
                    Settings::set($settingKey, $url, 'branding');
                } else {
                    $uploadError = true;
                }
            }
        }

        $this->audit('settings.update');
        if ($uploadError) {
            Session::flash('err', 'Image upload failed: use PNG/JPG/WEBP/SVG/ICO under 2 MB.');
        } else {
            Session::flash('ok', 'Settings saved.');
        }
        $this->redirect(base_url('/admin/settings'));
    }

    /** Store an uploaded branding image and return its public URL, or null. */
    private function handleUpload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > 2 * 1024 * 1024) {
            return null;
        }
        $allowed = ['png' => 'png', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'gif' => 'gif',
                    'webp' => 'webp', 'svg' => 'svg', 'ico' => 'ico'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            return null;
        }
        $dir = Config::get('paths.public') . '/assets/uploads';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $name = 'brand-' . bin2hex(random_bytes(6)) . '.' . $allowed[$ext];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return null;
        }
        return base_url('/assets/uploads/' . $name);
    }
}
