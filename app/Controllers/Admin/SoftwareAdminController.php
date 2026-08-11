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
    /** GET /admin/software/new — blank add-software form. */
    public function create(array $args = []): never
    {
        $this->requirePermission('software.manage');
        $this->render('admin/software/create', [
            'title'      => 'Add Software',
            'categories' => Category::all(),
            'oss'        => Database::all('SELECT * FROM operating_systems ORDER BY sort_order'),
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

        // Operating systems (checkboxes) -> label + m2m rows.
        $osIds = array_filter(array_map('intval', (array) $this->request->input('os', [])));
        $osLabel = $osIds ? Classifier::osLabel($osIds) : $this->request->str('operating_system');

        $data = [
            'name'                  => $name,
            'developer_name'        => $this->request->str('developer_name') ?: null,
            'developer_website'     => $this->request->str('developer_website') ?: null,
            'official_website'      => $this->request->str('official_website') ?: null,
            'official_download_url' => $this->request->str('official_download_url') ?: null,
            'short_description'     => $this->request->str('short_description') ?: null,
            'long_description'      => (string) $this->request->input('long_description', '') ?: null,
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
            'logo'                  => $this->request->str('logo') ?: null,
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

        Seo::generateForSoftware($id);
        if ($data['status'] === 'published') {
            Sitemap::generateAll();
        }
        $this->audit('software.create', 'software', $id, $name);

        Session::flash('ok', 'Software added.');
        $this->redirect(base_url('/admin/software/' . $id . '/edit'));
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
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = :st';
            $params['st'] = $status;
        }
        if ($q !== '') {
            $where[] = '(name LIKE :q OR developer_name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $page = $this->page();
        $offset = ($page - 1) * 30;
        $whereSql = implode(' AND ', $where);

        $items = Database::all("SELECT * FROM software WHERE $whereSql ORDER BY updated_at DESC LIMIT 30 OFFSET $offset", $params);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM software WHERE $whereSql", $params);

        $this->render('admin/software/index', [
            'title'   => 'Software',
            'items'   => $items,
            'total'   => $total,
            'page'    => $page,
            'perPage' => 30,
            'status'  => $status,
            'q'       => $q,
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
