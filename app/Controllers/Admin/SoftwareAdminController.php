<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Models\Category;
use App\Models\Software;
use App\Services\Classifier;
use App\Services\LinkChecker;
use App\Services\Seo;
use App\Services\Sitemap;
use App\Services\TrustScore;

final class SoftwareAdminController extends AdminController
{
    /** GET /admin/software/lookup?name=… — free auto-fill from public catalogues. */
    public function lookup(array $args = []): never
    {
        $this->requirePermission('software.manage');
        $name = trim($this->request->str('name'));
        if (mb_strlen($name) < 2) {
            $this->json(['ok' => false, 'message' => 'Please type a software name first.']);
        }
        $data = \App\Services\SoftwareLookup::search($name);
        if ($data === null) {
            $this->json(['ok' => false, 'message' => 'No official details found — please fill the form manually.']);
        }
        // Build free rich content (features / pros-cons / tags / long description) — no AI key needed.
        $facts = [
            'name'              => $data['name'] ?? $name,
            'developer_name'    => $data['developer_name'] ?? '',
            'short_description' => $data['short_description'] ?? '',
            'long_description'  => $data['long_description'] ?? '',
            'price_type'        => $data['price_type'] ?? '',
            'license_type'      => $data['license_type'] ?? '',
            'operating_system'  => $data['operating_system'] ?? '',
            'category_name'     => !empty($data['category_id'])
                ? (string) \App\Core\Database::scalar('SELECT name FROM categories WHERE id = :i', ['i' => (int) $data['category_id']])
                : '',
        ];
        $site = trim((string) ($data['official_website'] ?? ''));
        $rich = $site !== '' ? \App\Services\FreeContent::fromUrl($site, $facts) : \App\Services\FreeContent::fromFacts($facts);
        if (!empty($rich['long_description'])) {
            $data['long_description'] = $rich['long_description'];
        }
        $data['features'] = $rich['features'];
        $data['pros']     = $rich['pros'];
        $data['cons']     = $rich['cons'];
        $data['tags']     = $rich['tags'];
        $this->json(['ok' => true, 'data' => $data]);
    }

    /** GET /admin/software/ai-fill?name=… — catalogue lookup + AI-written rich content. */
    public function aiFill(array $args = []): never
    {
        $this->requirePermission('software.manage');
        $name = trim($this->request->str('name'));
        if (mb_strlen($name) < 2) {
            $this->json(['ok' => false, 'message' => 'Type a software name first.']);
        }
        $lookup = \App\Services\SoftwareLookup::search($name);
        $out = [
            'ok'           => true,
            'lookup'       => $lookup ?: null,
            'ai'           => null,
            'ai_available' => \App\Services\AiEnhancer::isConfigured(),
        ];
        if (\App\Services\AiEnhancer::isConfigured()) {
            $facts = array_filter([
                'name'             => $lookup['name'] ?? $name,
                'developer'        => $lookup['developer_name'] ?? null,
                'operating_system' => $lookup['operating_system'] ?? null,
                'license'          => $lookup['license_type'] ?? null,
                'price_type'       => $lookup['price_type'] ?? null,
                'official_website' => $lookup['official_website'] ?? null,
                'existing_short'   => $lookup['short_description'] ?? null,
            ], static fn($v) => $v !== null && $v !== '');
            $gen = \App\Services\AiEnhancer::generate($facts);
            if ($gen['ok']) {
                $out['ai'] = $gen['data'];
            } else {
                $out['ai_message'] = $gen['message'];
            }
        }
        $this->json($out);
    }

    /** Make sure the iOS platform row exists (older installs lack it). */
    private function ensureIos(): void
    {
        try {
            Database::run("INSERT IGNORE INTO operating_systems (name, slug, icon, sort_order) VALUES ('iOS','ios','',5)");
        } catch (\Throwable $e) {}
    }

    /** OS id for the currently-open platform panel, or 0. */
    private function panelOsId(): int
    {
        $slug = (string) Session::get('admin_platform', '');
        if ($slug === '') {
            return 0;
        }
        return (int) (Database::scalar('SELECT id FROM operating_systems WHERE slug = :s', ['s' => $slug]) ?: 0);
    }

    /** Add the optional video_url + auto_update columns once, if missing. */
    private function ensureColumns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [
            'video_url'   => "ALTER TABLE software ADD COLUMN video_url VARCHAR(500) NULL",
            'auto_update' => "ALTER TABLE software ADD COLUMN auto_update TINYINT(1) NOT NULL DEFAULT 0",
        ];
        foreach ($cols as $col => $sql) {
            $exists = Database::scalar(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'software' AND COLUMN_NAME = :c", ['c' => $col]);
            if ((int) $exists === 0) {
                try { Database::run($sql); } catch (\Throwable $e) {}
            }
        }
    }

    /** Validate + store one uploaded image; returns a root-relative URL or null. */
    private function saveImage(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > 4 * 1024 * 1024) {
            return null;
        }
        $allowed = ['png' => 'png', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'gif' => 'gif', 'webp' => 'webp', 'svg' => 'svg', 'ico' => 'ico'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            return null;
        }
        $dir = \App\Core\Config::get('paths.public') . '/assets/uploads';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $nm = 'sw-' . bin2hex(random_bytes(6)) . '.' . $allowed[$ext];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nm)) {
            return null;
        }
        return '/assets/uploads/' . $nm; // root-relative so it loads on every subdomain
    }

    /** Store uploaded screenshots for a software id. */
    private function saveScreenshots(int $id): void
    {
        if (empty($_FILES['screenshots']['name']) || !is_array($_FILES['screenshots']['name'])) {
            return;
        }
        $order = (int) Database::scalar('SELECT COALESCE(MAX(sort_order),0) FROM software_screenshots WHERE software_id = :s', ['s' => $id]);
        $f = $_FILES['screenshots'];
        $n = count($f['name']);
        for ($i = 0; $i < $n && $i < 12; $i++) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $url = $this->saveImage([
                'name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i],
                'size' => $f['size'][$i], 'error' => $f['error'][$i],
            ]);
            if ($url) {
                Database::run('INSERT INTO software_screenshots (software_id, url, sort_order) VALUES (:s, :u, :o)',
                    ['s' => $id, 'u' => $url, 'o' => ++$order]);
            }
        }
    }

    /** Save features / pros / cons (one item per line) for a software id. */
    private function saveFeatures(int $id): void
    {
        foreach (['feature' => 'features', 'pro' => 'pros', 'con' => 'cons'] as $type => $field) {
            $raw = (string) $this->request->input($field, '');
            $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
            if (!$lines) {
                continue;
            }
            Database::run('DELETE FROM software_features WHERE software_id = :s AND `type` = :t', ['s' => $id, 't' => $type]);
            $o = 0;
            foreach (array_slice($lines, 0, 12) as $label) {
                Database::run('INSERT INTO software_features (software_id, `type`, label, sort_order) VALUES (:s, :t, :l, :o)',
                    ['s' => $id, 't' => $type, 'l' => mb_substr($label, 0, 300), 'o' => $o++]);
            }
        }
    }

    /** Save comma-separated tags for a software id. */
    private function saveTags(int $id): void
    {
        $raw = (string) $this->request->input('tags', '');
        $names = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
        if (!$names) {
            return;
        }
        Database::run('DELETE FROM software_tags WHERE software_id = :s', ['s' => $id]);
        foreach (array_slice($names, 0, 20) as $name) {
            $slug = slugify($name);
            if ($slug === '') {
                continue;
            }
            $tagId = (int) Database::scalar('SELECT id FROM tags WHERE slug = :s', ['s' => $slug]);
            if (!$tagId) {
                Database::run('INSERT IGNORE INTO tags (name, slug) VALUES (:n, :s)', ['n' => mb_substr($name, 0, 80), 's' => $slug]);
                $tagId = (int) Database::scalar('SELECT id FROM tags WHERE slug = :s', ['s' => $slug]);
            }
            if ($tagId) {
                Database::run('INSERT IGNORE INTO software_tags (software_id, tag_id) VALUES (:s, :t)', ['s' => $id, 't' => $tagId]);
            }
        }
    }

    /** POST /admin/software/quick — one-click: auto-fill from name, then publish. */
    public function quickPublish(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $name = trim($this->request->str('name'));
        if (mb_strlen($name) < 2) {
            Session::flash('err', 'Type a software name first.');
            $this->redirect(base_url('/admin/software/new'));
        }
        $this->ensureIos();
        $this->ensureColumns();
        $res = \App\Services\Publisher::publish($name, null, (string) Session::get('admin_platform', '') ?: null);
        if (($res['id'] ?? 0) > 0) {
            Sitemap::generateAll();
            $this->audit('software.quick', 'software', (int) $res['id'], $res['name']);
            Session::flash('ok', '✨ Published "' . $res['name'] . '"' . (\App\Services\AiEnhancer::isConfigured() ? ' with AI' : '') . '.');
            $this->redirect(base_url('/admin/software/' . $res['id'] . '/edit'));
        }
        Session::flash('err', $res['status'] === 'duplicate'
            ? '"' . $res['name'] . '" is already published.'
            : 'No official details found for "' . $name . '". Add it manually below.');
        $this->redirect(base_url('/admin/software/new'));
    }

    /** POST /admin/software/publish-one — publish one item (name or URL), for bulk. */
    public function publishOne(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $this->ensureIos();
        $this->ensureColumns();

        $name = trim($this->request->str('name'));
        $url = trim($this->request->str('url'));
        if ($name === '' && $url === '') {
            $this->json(['ok' => false, 'status' => 'error', 'name' => '', 'message' => 'empty']);
        }
        $res = \App\Services\Publisher::publish($name, $url ?: null, (string) Session::get('admin_platform', '') ?: null);
        if (($res['id'] ?? 0) > 0) {
            $this->audit('software.bulk', 'software', (int) $res['id'], $res['name']);
        }
        $this->json(['ok' => true] + $res);
    }

    /** POST /admin/software/queue — add names/URLs to the background queue. */
    public function queueBackground(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->request->input('names', '')) ?: [];
        $n = \App\Services\PublishQueue::add($lines, (string) Session::get('admin_platform', '') ?: null);
        $this->audit('software.queue', null, null, "queued $n");
        Session::flash('ok', "🚀 $n software queued — the hourly cron will publish them in the background. You can close this page.");
        $this->redirect(base_url('/admin/software/bulk'));
    }

    /** POST /admin/software/pack — queue a curated pack (by category slug). */
    public function pack(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $slug = $this->request->str('pack');
        $names = [];
        foreach (\App\Services\CatalogImport::popularAll() as $a) {
            if (($a[5] ?? '') === $slug) {
                $names[] = $a[0];
            }
        }
        $n = \App\Services\PublishQueue::add($names, (string) Session::get('admin_platform', '') ?: null);
        $this->audit('software.pack', null, null, "$slug: $n");
        Session::flash('ok', "🗂️ Pack queued — $n apps will publish in the background.");
        $this->redirect(base_url('/admin/software/bulk'));
    }

    /** POST /admin/software/ai-category — AI lists software for a topic, then queues them. */
    public function aiCategory(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $request = trim($this->request->str('request'));
        $count = max(5, min(100, $this->request->int('count', 30)));
        if ($request === '') {
            Session::flash('err', 'Describe what you want, e.g. "50 popular PDF tools".');
            $this->redirect(base_url('/admin/software/bulk'));
        }
        if (!\App\Services\AiEnhancer::isConfigured()) {
            Session::flash('err', 'Add your Anthropic API key in Settings to use AI category fill.');
            $this->redirect(base_url('/admin/software/bulk'));
        }
        $names = \App\Services\AiEnhancer::softwareList($request, $count);
        if (!$names) {
            Session::flash('err', 'AI could not produce a list — try rephrasing.');
            $this->redirect(base_url('/admin/software/bulk'));
        }
        $n = \App\Services\PublishQueue::add($names, (string) Session::get('admin_platform', '') ?: null);
        $this->audit('software.ai_category', null, null, "$request: $n");
        Session::flash('ok', "🤖 AI listed $n software for \"$request\" — queued to publish in the background.");
        $this->redirect(base_url('/admin/software/bulk'));
    }

    /** GET /admin/software/import-url?url=… — read a software's official page. */
    public function importUrl(array $args = []): never
    {
        $this->requirePermission('software.manage');
        $url = trim($this->request->str('url'));
        if ($url === '') {
            $this->json(['ok' => false, 'message' => 'Paste the official website URL first.']);
        }
        $page = \App\Services\Publisher::fetch($url);
        if ($page === null) {
            $this->json(['ok' => false, 'message' => 'Could not read that page — check the URL.']);
        }
        $d = \App\Services\Publisher::parseHtml($page['html'], $page['url']);
        if ($d === null) {
            $this->json(['ok' => false, 'message' => 'Could not read that page — check the URL.']);
        }
        $catId = Classifier::detectCategory((string) ($d['name'] ?? ''), (string) ($d['long_description'] ?? ''));
        $d['category_id'] = $catId;
        // Build features / pros-cons / tags / long description from the real page — no AI key needed.
        $rich = \App\Services\FreeContent::build($page['html'], [
            'name'             => $d['name'] ?? '',
            'developer_name'   => $d['developer_name'] ?? '',
            'short_description' => $d['short_description'] ?? '',
            'long_description' => $d['long_description'] ?? '',
            'price_type'       => $d['price_type'] ?? '',
            'license_type'     => $d['license_type'] ?? '',
            'operating_system' => $d['operating_system'] ?? '',
            'category_name'    => $catId ? (string) \App\Core\Database::scalar('SELECT name FROM categories WHERE id = :i', ['i' => $catId]) : '',
        ]);
        if (!empty($rich['long_description'])) {
            $d['long_description'] = $rich['long_description'];
        }
        $out = array_filter($d, static fn($v) => $v !== null && $v !== '');
        $out['features'] = $rich['features'];
        $out['pros']     = $rich['pros'];
        $out['cons']     = $rich['cons'];
        $out['tags']     = $rich['tags'];
        $this->json(['ok' => true, 'data' => $out]);
    }

    /** POST /admin/software/category — create a category inline; returns JSON. */
    public function addCategory(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $name = trim($this->request->str('name'));
        if (mb_strlen($name) < 2) {
            $this->json(['ok' => false, 'message' => 'Enter a category name.']);
        }
        $slug = slugify($name);
        $existing = Database::first('SELECT id, name FROM categories WHERE slug = :s', ['s' => $slug]);
        if ($existing) {
            $this->json(['ok' => true, 'id' => (int) $existing['id'], 'name' => $existing['name']]);
        }
        Database::run('INSERT INTO categories (name, slug, status, sort_order) VALUES (:n, :s, "active", 100)',
            ['n' => mb_substr($name, 0, 120), 's' => $slug]);
        $id = (int) Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $slug]);
        $this->audit('category.create', 'category', $id, $name);
        $this->json(['ok' => true, 'id' => $id, 'name' => $name]);
    }

    /** Default OS versions offered in the multi-select, merged with saved ones. */
    private function osVersionOptions(): array
    {
        $defaults = [
            'Windows 11', 'Windows 10', 'Windows 8.1', 'Windows 7',
            'macOS 15 (Sequoia)', 'macOS 14 (Sonoma)', 'macOS 13 (Ventura)',
            'iOS 18', 'iOS 17', 'iPadOS 18',
            'Android 15', 'Android 14', 'Android 13',
            'Linux', 'Chrome OS',
        ];
        $saved = [];
        $raw = (string) \App\Core\Settings::get('os_versions', '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $saved = array_map('strval', $decoded);
            }
        }
        // Saved first (newest custom entries surface at the top), then defaults.
        $all = array_merge($saved, $defaults);
        $seen = [];
        $out = [];
        foreach ($all as $v) {
            $v = trim($v);
            $k = mb_strtolower($v);
            if ($v !== '' && !isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $v;
            }
        }
        return $out;
    }

    /** Persist any newly-used OS versions so they appear in the dropdown next time. */
    private function rememberOsVersions(array $versions): void
    {
        $current = $this->osVersionOptions();
        $known = [];
        foreach ($current as $v) {
            $known[mb_strtolower($v)] = true;
        }
        $added = false;
        $custom = [];
        $raw = (string) \App\Core\Settings::get('os_versions', '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $custom = array_map('strval', $decoded);
            }
        }
        foreach ($versions as $v) {
            $v = trim((string) $v);
            if ($v !== '' && mb_strlen($v) <= 60 && !isset($known[mb_strtolower($v)])) {
                array_unshift($custom, $v);
                $known[mb_strtolower($v)] = true;
                $added = true;
            }
        }
        if ($added) {
            \App\Core\Settings::set('os_versions', array_slice($custom, 0, 60), 'software', 'json');
        }
    }

    /** POST /admin/software/os-version — add an OS version to the dropdown; returns the list. */
    public function osVersion(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $name = trim($this->request->str('name'));
        if (mb_strlen($name) < 2) {
            $this->json(['ok' => false, 'message' => 'Enter an OS version.']);
        }
        $this->rememberOsVersions([$name]);
        $this->json(['ok' => true, 'name' => mb_substr($name, 0, 60), 'options' => $this->osVersionOptions()]);
    }

    /** POST /admin/software/trending — queue recently-trending open-source apps. */
    public function trending(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $token = (string) \App\Core\Config::get('integrations.github_token', '');
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $q = 'stars:>2000 pushed:>' . gmdate('Y-m-d', time() - 30 * 86400) . ' topic:desktop';
        $resp = \App\Support\Http::get(
            'https://api.github.com/search/repositories?per_page=25&sort=updated&order=desc&q=' . rawurlencode($q), $headers, 20);
        $data = json_decode($resp['body'] ?? '', true);
        $names = [];
        foreach (($data['items'] ?? []) as $r) {
            if (!empty($r['name'])) {
                $names[] = (string) $r['name'];
            }
        }
        $n = \App\Services\PublishQueue::add($names, (string) Session::get('admin_platform', '') ?: null);
        $this->audit('software.trending', null, null, (string) $n);
        Session::flash($n ? 'ok' : 'err', $n ? "📈 Queued $n trending apps to publish in the background." : 'Could not fetch trending apps right now.');
        $this->redirect(base_url('/admin/software/bulk'));
    }

    /** GET /admin/software/suggest?q=… — name autocomplete. */
    public function suggest(array $args = []): never
    {
        $this->requirePermission('software.manage');
        $q = trim($this->request->str('q'));
        if (mb_strlen($q) < 2) {
            $this->json(['items' => []]);
        }
        $qn = strtolower($q);
        $items = [];
        foreach (\App\Services\CatalogImport::popularAll() as $a) {
            if (str_contains(strtolower($a[0]), $qn)) {
                $items[$a[0]] = true;
            }
            if (count($items) >= 8) {
                break;
            }
        }
        if (count($items) < 8) {
            foreach (Database::all('SELECT DISTINCT name FROM software WHERE name LIKE :q ORDER BY name LIMIT 8', ['q' => '%' . $q . '%']) as $r) {
                $items[$r['name']] = true;
                if (count($items) >= 10) {
                    break;
                }
            }
        }
        $this->json(['items' => array_keys($items)]);
    }

    /** GET /admin/software/bulk — bulk / fast publishing page. */
    public function bulk(array $args = []): never
    {
        $this->requirePermission('software.manage');
        \App\Services\Dedupe::ensureSchema();
        \App\Services\Dedupe::backfill();

        // Popular apps still missing, and ready-made packs grouped by category.
        $missing = [];
        $packCount = [];
        foreach (\App\Services\CatalogImport::popularAll() as $a) {
            $cat = $a[5] ?? 'other';
            $packCount[$cat] = ($packCount[$cat] ?? 0) + 1;
            $key = \App\Services\Dedupe::key($a[0]);
            if (count($missing) < 24 && $key !== '' && !Database::scalar('SELECT id FROM software WHERE dedupe_key = :k LIMIT 1', ['k' => $key])) {
                $missing[] = $a[0];
            }
        }
        arsort($packCount);

        $this->render('admin/software/bulk', [
            'title'      => 'Bulk publish',
            'missing'    => $missing,
            'packs'      => $packCount,
            'queue'      => \App\Services\PublishQueue::stats(),
            'aiReady'    => \App\Services\AiEnhancer::isConfigured(),
            'panelLabel' => \App\Controllers\Admin\PlatformController::current()['label'] ?? null,
        ]);
    }

    /** GET /admin/publishing — a small publishing dashboard. */
    public function dashboard(array $args = []): never
    {
        $this->requirePermission('software.view');
        $osLike = ['windows' => '%windows%', 'macos' => '%mac%', 'ios' => '%ios%', 'android' => '%android%'];
        $perOs = [];
        foreach ($osLike as $slug => $like) {
            $perOs[$slug] = (int) Database::scalar('SELECT COUNT(*) FROM software WHERE operating_system LIKE :l', ['l' => $like]);
        }
        $spark = [];
        foreach (Database::all(
            "SELECT DATE(created_at) d, COUNT(*) c FROM software
             WHERE created_at >= (CURRENT_DATE - INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY d") as $r) {
            $spark[$r['d']] = (int) $r['c'];
        }
        $this->render('admin/software/dashboard', [
            'title'   => 'Publishing dashboard',
            'total'   => (int) Database::scalar('SELECT COUNT(*) FROM software'),
            'today'   => (int) Database::scalar('SELECT COUNT(*) FROM software WHERE DATE(created_at) = CURRENT_DATE'),
            'week'    => (int) Database::scalar('SELECT COUNT(*) FROM software WHERE created_at >= (CURRENT_DATE - INTERVAL 7 DAY)'),
            'perOs'   => $perOs,
            'spark'   => $spark,
            'queue'   => \App\Services\PublishQueue::stats(),
        ]);
    }

    /** GET /admin/software/new — blank add-software form. */
    public function create(array $args = []): never
    {
        $this->requirePermission('software.manage');
        $this->ensureIos();
        // Pre-select the open panel's platform (or an explicit ?os=).
        $slug = $this->request->str('os') ?: (string) Session::get('admin_platform', '');
        $preOs = (int) (Database::scalar('SELECT id FROM operating_systems WHERE slug = :s', ['s' => $slug]) ?: 0);
        $this->render('admin/software/create', [
            'title'      => 'Add Software',
            'categories' => Category::all(),
            'oss'        => Database::all('SELECT * FROM operating_systems ORDER BY sort_order'),
            'preOs'      => $preOs,
            'osVersions' => $this->osVersionOptions(),
            'panelLabel' => \App\Controllers\Admin\PlatformController::current()['label'] ?? null,
        ]);
    }

    /** POST /admin/software/new — create a software record by hand. */
    public function store(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);

        $name = $this->request->str('name');
        if ($name === '') {
            Session::flash('err', 'Software name is required.');
            $this->redirect(base_url('/admin/software/new'));
        }

        // Operating systems (checkboxes) -> label + m2m rows. When none are
        // ticked, default to the platform panel the admin is in, so the software
        // publishes to THAT platform's site only (not the others).
        $this->ensureIos();
        $this->ensureColumns();
        $osIds = array_filter(array_map('intval', (array) $this->request->input('os', [])));
        if (!$osIds && ($panelOs = $this->panelOsId()) > 0) {
            $osIds = [$panelOs];
        }
        // Detailed OS versions the admin picked from the multi-select (or typed).
        $osVersions = array_values(array_filter(array_map(
            static fn($v) => trim((string) $v),
            (array) $this->request->input('os_versions', [])
        )));
        $osText = $osVersions ? implode(', ', array_slice($osVersions, 0, 12)) : $this->request->str('operating_system');
        // Text column: prefer the specific versions; fall back to the platform label.
        $osLabel = $osText !== '' ? $osText : ($osIds ? Classifier::osLabel($osIds) : '');
        if ($osVersions) {
            $this->rememberOsVersions($osVersions);
        }

        // Logo: an uploaded file wins over the URL field.
        $logo = $this->request->str('logo') ?: null;
        if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
            $up = $this->saveImage($_FILES['logo_file']);
            if ($up) { $logo = $up; }
        }

        $data = [
            'name'                  => $name,
            'developer_name'        => $this->request->str('developer_name') ?: null,
            'developer_website'     => $this->request->str('developer_website') ?: null,
            'official_website'      => $this->request->str('official_website') ?: null,
            'official_download_url' => $this->request->str('official_download_url') ?: null,
            'short_description'     => $this->request->str('short_description') ?: null,
            'long_description'      => sanitize_rich((string) $this->request->input('long_description', '')) ?: null,
            'version'               => $this->request->str('version') ?: null,
            'release_date'          => $this->request->str('release_date') ?: null,
            'license_type'          => $this->request->str('license_type') ?: null,
            'price_type'            => $this->request->str('price_type') ?: null,
            'is_open_source'        => $this->request->str('is_open_source') === '1' ? 1 : 0,
            'file_size'             => $this->request->str('file_size') ?: null,
            'architecture'          => $this->request->str('architecture') ?: null,
            'operating_system'      => $osLabel ?: null,
            'min_ram_mb'            => $this->request->int('min_ram_mb') ?: null,
            'minimum_requirements'  => (string) $this->request->input('minimum_requirements', '') ?: null,
            'category_id'           => $this->request->int('category_id') ?: null,
            'logo'                  => $logo,
            'video_url'             => $this->request->str('video_url') ?: null,
            'auto_update'           => $this->request->str('auto_update') === '1' ? 1 : 0,
            'source_type'           => 'manual',
        ];

        // Scores (for display / filtering) + status chosen by the admin.
        $data['trust_score']         = TrustScore::compute($data);
        $data['quality_score']       = TrustScore::quality($data);
        $data['verification_status'] = $data['trust_score'] >= 70 ? 'verified'
            : ($data['trust_score'] >= 40 ? 'review' : 'unverified');
        $data['status']       = $this->request->str('status', 'published');
        \App\Services\Dedupe::ensureSchema();
        $data['dedupe_key']   = \App\Services\Dedupe::key($name);
        $data['slug']         = $this->uniqueSlug(slugify($name));
        $data['discovered_at'] = gmdate('Y-m-d H:i:s');
        $data['last_checked_at'] = gmdate('Y-m-d H:i:s');
        $data['last_updated'] = $data['release_date'] ?: gmdate('Y-m-d H:i:s');

        $id = Database::insert('software', array_filter($data, static fn($v) => $v !== null));

        // Version history row.
        if (!empty($data['version'])) {
            try {
                Database::run(
                    'INSERT INTO software_versions (software_id, version, normalized, release_date, download_url, file_size, is_current)
                     VALUES (:sid, :v, :n, :rd, :du, :fs, 1)',
                    [
                        'sid' => $id, 'v' => $data['version'],
                        'n' => \App\Support\Version::normalize($data['version']),
                        'rd' => $data['release_date'], 'du' => $data['official_download_url'],
                        'fs' => $data['file_size'],
                    ]
                );
            } catch (\Throwable $e) {
            }
        }

        // OS mapping.
        foreach ($osIds as $osId) {
            try {
                Database::run('INSERT IGNORE INTO software_operating_systems (software_id, os_id) VALUES (:s, :o)',
                    ['s' => $id, 'o' => $osId]);
            } catch (\Throwable $e) {
            }
        }

        // Screenshots, features/pros/cons, and tags.
        try { $this->saveScreenshots($id); } catch (\Throwable $e) {}
        try { $this->saveFeatures($id); } catch (\Throwable $e) {}
        try { $this->saveTags($id); } catch (\Throwable $e) {}

        Seo::generateForSoftware($id);
        if ($data['status'] === 'published') {
            Sitemap::generateAll();
        }
        $this->audit('software.create', 'software', $id, $name);

        // "Save & add another" keeps you on a fresh form for rapid entry.
        if ($this->request->str('and_new') === '1') {
            Session::flash('ok', '✓ Added "' . $name . '". Add the next one…');
            $this->redirect(base_url('/admin/software/new'));
        }
        Session::flash('ok', 'Software added.');
        $this->redirect(base_url('/admin/software/' . $id . '/edit?published=1'));
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Database::scalar('SELECT id FROM software WHERE slug = :s', ['s' => $slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function index(array $args = []): never
    {
        $this->requirePermission('software.view');

        $status = $this->request->str('status');
        $q = $this->request->str('q');
        // Platform: no param -> the panel chosen at login (session); 'all' -> no
        // filter; a slug -> that platform (and remember it as the active panel).
        $osParam = $this->request->query('os');
        if ($osParam === null) {
            $os = (string) \App\Core\Session::get('admin_platform', '');
        } elseif ($osParam === 'all') {
            $os = '';
        } else {
            $os = (string) $osParam;
        }
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = :st';
            $params['st'] = $status;
        }
        if ($q !== '') {
            $where[] = '(name LIKE :q OR developer_name LIKE :q2)';
            $params['q'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }
        $osLike = ['windows' => '%windows%', 'macos' => '%mac%', 'ios' => '%ios%', 'android' => '%android%'];
        if (isset($osLike[$os])) {
            $where[] = 'operating_system LIKE :os';
            $params['os'] = $osLike[$os];
            \App\Core\Session::set('admin_platform', $os); // keep the active panel in sync
        } elseif ($osParam === 'all') {
            \App\Core\Session::forget('admin_platform');
        }
        $page = $this->page();
        $offset = ($page - 1) * 30;
        $whereSql = implode(' AND ', $where);

        $items = Database::all("SELECT * FROM software WHERE $whereSql ORDER BY updated_at DESC LIMIT 30 OFFSET $offset", $params);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM software WHERE $whereSql", $params);

        // Per-platform counts for the tab labels.
        $counts = [];
        foreach ($osLike as $slug => $like) {
            $counts[$slug] = (int) Database::scalar(
                'SELECT COUNT(*) FROM software WHERE operating_system LIKE :l', ['l' => $like]
            );
        }
        $counts['all'] = (int) Database::scalar('SELECT COUNT(*) FROM software');

        $this->render('admin/software/index', [
            'title'   => 'Software',
            'items'   => $items,
            'total'   => $total,
            'page'    => $page,
            'perPage' => 30,
            'status'  => $status,
            'q'       => $q,
            'os'      => $os,
            'counts'  => $counts,
        ]);
    }

    public function edit(array $args): never
    {
        $this->requirePermission('software.manage');
        $software = Software::find((int) ($args['id'] ?? 0));
        if ($software === null) {
            $this->render('admin/software/index', ['title' => 'Not found', 'items' => [], 'total' => 0, 'page' => 1, 'perPage' => 30, 'status' => '', 'q' => '']);
        }
        $this->render('admin/software/edit', [
            'title'      => 'Edit: ' . $software['name'],
            'software'   => $software,
            'categories' => Category::all(),
            'oss'        => Database::all('SELECT * FROM operating_systems ORDER BY sort_order'),
        ]);
    }

    public function update(array $args): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $id = (int) ($args['id'] ?? 0);
        $software = Software::find($id);
        if ($software === null) {
            $this->redirect(base_url('/admin/software'));
        }

        $fields = ['name', 'developer_name', 'developer_website', 'official_website',
            'official_download_url', 'short_description', 'long_description', 'version',
            'license_type', 'price_type', 'architecture', 'operating_system',
            'minimum_requirements', 'file_size'];
        $data = [];
        foreach ($fields as $f) {
            $data[$f] = $this->request->input($f, $software[$f]);
        }
        $data['category_id'] = $this->request->int('category_id') ?: null;
        $data['min_ram_mb'] = $this->request->int('min_ram_mb') ?: null;
        $data['is_open_source'] = $this->request->str('is_open_source') === '1' ? 1 : 0;
        $data['long_description'] = sanitize_rich((string) $data['long_description']);
        $data['status'] = $this->request->str('status', $software['status']);
        \App\Services\Dedupe::ensureSchema();
        $data['dedupe_key'] = \App\Services\Dedupe::key((string) $data['name']);

        Database::update('software', $data, ['id' => $id]);
        Seo::generateForSoftware($id);
        $this->audit('software.update', 'software', $id);

        \App\Core\Session::flash('ok', 'Software updated.');
        $this->redirect(base_url('/admin/software/' . $id . '/edit'));
    }

    /** POST /admin/software/{id}/action */
    public function action(array $args): never
    {
        Csrf::check($this->request);
        $id = (int) ($args['id'] ?? 0);
        $action = $this->request->str('action');
        $software = Software::find($id);
        if ($software === null) {
            $this->redirect(base_url('/admin/software'));
        }

        switch ($action) {
            case 'approve':
                $this->requirePermission('software.review');
                Database::update('software', ['status' => 'published'], ['id' => $id]);
                Seo::generateForSoftware($id);
                Sitemap::generateAll();
                break;
            case 'reject':
                $this->requirePermission('software.review');
                Database::update('software', ['status' => 'rejected'], ['id' => $id]);
                break;
            case 'disable':
                $this->requirePermission('software.manage');
                Database::update('software', ['status' => 'disabled'], ['id' => $id]);
                break;
            case 'delete':
                $this->requirePermission('software.manage');
                Database::delete('software', ['id' => $id]);
                Sitemap::generateAll();
                break;
            case 'recheck':
                $this->requirePermission('software.manage');
                LinkChecker::checkUrl($software['official_download_url'] ?: (string) $software['official_website']);
                Database::update('software', ['last_checked_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]);
                break;
            case 'rebuild_seo':
                $this->requirePermission('seo.manage');
                Seo::generateForSoftware($id);
                break;
        }

        $this->audit('software.' . $action, 'software', $id);

        if ($action === 'delete') {
            Session::flash('ok', 'Software deleted.');
            $this->redirect(base_url('/admin/software'));
        }

        Session::flash('ok', 'Action "' . $action . '" applied.');
        // Avoid redirecting back to a now-stale edit page after a status change.
        $referer = (string) $this->request->header('Referer');
        $this->redirect($referer !== '' ? $referer : base_url('/admin/software'));
    }
}
