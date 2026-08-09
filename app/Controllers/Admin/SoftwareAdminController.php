<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Category;
use App\Models\Software;
use App\Services\LinkChecker;
use App\Services\Seo;
use App\Services\Sitemap;

final class SoftwareAdminController extends AdminController
{
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
        \App\Core\Session::flash('ok', 'Action "' . $action . '" applied.');
        $this->redirect($this->request->header('Referer') ?: base_url('/admin/software'));
    }
}
