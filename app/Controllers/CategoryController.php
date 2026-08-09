<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Software;

final class CategoryController extends Controller
{
    /** GET /categories */
    public function index(array $args = []): never
    {
        $this->trackView('/categories');
        $this->view('pages/categories', [
            'title'           => 'Software Categories',
            'metaDescription' => 'Browse software by category — from PDF tools to video editing, security and developer tools.',
            'categories'      => Category::withCounts(),
        ]);
    }

    /** GET /category/{slug} */
    public function show(array $args): never
    {
        $category = Category::findBySlug($args['slug'] ?? '');
        if ($category === null) {
            (new ErrorController($this->request))->notFound();
        }

        $page = $this->page();
        $result = Software::filter(['category_id' => (int) $category['id'], 'sort' => $this->request->str('sort', 'popular')], $page, 24);

        $this->trackView('/category/' . $category['slug'], 'category', (int) $category['id']);
        $this->view('pages/category', [
            'title'           => ($category['meta_title'] ?: $category['name'] . ' Software'),
            'metaDescription' => $category['meta_desc'] ?: ('Best ' . $category['name'] . ' software — compare, discover alternatives and download from official sources.'),
            'canonical'       => base_url('/category/' . $category['slug']),
            'category'        => $category,
            'children'        => Category::children((int) $category['id']),
            'items'           => $result['items'],
            'total'           => $result['total'],
            'page'            => $page,
            'perPage'         => 24,
            'baseUrl'         => '/category/' . $category['slug'],
        ]);
    }
}
