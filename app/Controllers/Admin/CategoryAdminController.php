<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;

final class CategoryAdminController extends AdminController
{
    /** GET /admin/categories — list + manage categories (sorted by name). */
    public function index(array $args = []): never
    {
        $this->requirePermission('software.manage');
        $cats = Database::all(
            'SELECT c.id, c.name, c.slug, c.status,
                    (SELECT COUNT(*) FROM software s WHERE s.category_id = c.id) AS cnt
             FROM categories c ORDER BY c.name'
        );
        $this->render('admin/categories/index', ['title' => 'Categories', 'cats' => $cats]);
    }

    /** POST /admin/categories — add a category. */
    public function store(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $name = trim($this->request->str('name'));
        if (mb_strlen($name) < 2) {
            Session::flash('err', 'Enter a category name.');
            $this->redirect(base_url('/admin/categories'));
        }
        $slug = slugify($name);
        if (!Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $slug])) {
            Database::run('INSERT INTO categories (name, slug, status, sort_order) VALUES (:n, :s, "active", 100)',
                ['n' => mb_substr($name, 0, 120), 's' => $slug]);
            $this->audit('category.create', 'category', null, $name);
            Session::flash('ok', 'Category "' . $name . '" added.');
        } else {
            Session::flash('err', 'That category already exists.');
        }
        $this->redirect(base_url('/admin/categories'));
    }

    /** POST /admin/categories/{id}/rename — rename a category. */
    public function rename(array $args): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $id = (int) ($args['id'] ?? 0);
        $name = trim($this->request->str('name'));
        if ($id <= 0 || mb_strlen($name) < 2) {
            Session::flash('err', 'Enter a valid name.');
            $this->redirect(base_url('/admin/categories'));
        }
        $slug = slugify($name);
        // Keep the slug unique across other categories.
        $base = $slug;
        $i = 2;
        while (Database::scalar('SELECT id FROM categories WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $id])) {
            $slug = $base . '-' . $i++;
        }
        Database::run('UPDATE categories SET name = :n, slug = :s WHERE id = :id',
            ['n' => mb_substr($name, 0, 120), 's' => $slug, 'id' => $id]);
        $this->audit('category.rename', 'category', $id, $name);
        Session::flash('ok', 'Category renamed to "' . $name . '".');
        $this->redirect(base_url('/admin/categories'));
    }

    /** POST /admin/categories/{id}/delete — delete a category (software becomes uncategorised). */
    public function delete(array $args): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $id = (int) ($args['id'] ?? 0);
        $cat = Database::first('SELECT name FROM categories WHERE id = :id', ['id' => $id]);
        if ($cat === null) {
            $this->redirect(base_url('/admin/categories'));
        }
        Database::run('UPDATE software SET category_id = NULL WHERE category_id = :id', ['id' => $id]);
        Database::run('DELETE FROM categories WHERE id = :id', ['id' => $id]);
        $this->audit('category.delete', 'category', $id, $cat['name']);
        Session::flash('ok', 'Category "' . $cat['name'] . '" deleted.');
        $this->redirect(base_url('/admin/categories'));
    }
}
