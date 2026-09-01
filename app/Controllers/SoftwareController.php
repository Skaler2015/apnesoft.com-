<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Models\Category;
use App\Models\Software;
use App\Services\Seo;

final class SoftwareController extends Controller
{
    /** GET /software  — filterable listing */
    public function index(array $args = []): never
    {
        $filters = [
            'q'            => $this->request->str('q'),
            'category_id'  => $this->request->int('category'),
            'os_slug'      => $this->request->str('os'),
            'price_type'   => $this->request->str('price'),
            'open_source'  => $this->request->str('open_source'),
            'max_ram'      => $this->request->int('max_ram'),
            'architecture' => $this->request->str('arch'),
            'trust_min'    => $this->request->int('trust'),
            'sort'         => $this->request->str('sort', 'popular'),
        ];

        $page = $this->page();
        $result = Software::filter($filters, $page, 24);

        $this->trackView('/software');
        $this->view('software/index', [
            'title'           => 'All Software',
            'metaDescription' => 'Browse and filter all software by operating system, category, license and system requirements.',
            'items'           => $result['items'],
            'total'           => $result['total'],
            'page'            => $page,
            'perPage'         => 24,
            'filters'         => $filters,
            'categories'      => Category::withCounts(),
            'baseUrl'         => '/software',
        ]);
    }

    /** GET /software/{slug} — detail page */
    public function show(array $args): never
    {
        $software = Software::findPublishedBySlug($args['slug'] ?? '');
        if ($software === null) {
            (new ErrorController($this->request))->notFound();
        }

        $id = (int) $software['id'];
        Software::incrementViews($id);
        $this->trackView('/software/' . $software['slug'], 'software', $id);

        $category = $software['category_id'] ? Category::findBySlug(
            (string) \App\Core\Database::scalar('SELECT slug FROM categories WHERE id = :id', ['id' => $software['category_id']])
        ) : null;

        $seo = Seo::forEntity('software', $id);
        $structured = $seo && $seo['structured'] ? json_decode($seo['structured'], true) : Seo::softwareSchema($software, base_url('/software/' . $software['slug']));

        $this->view('software/show', [
            'title'           => $seo['title'] ?? ($software['name'] . ' — Download'),
            'metaDescription' => $seo['description'] ?? str_excerpt($software['short_description'], 160),
            'canonical'       => $seo['canonical'] ?? base_url('/software/' . $software['slug']),
            'ogImage'         => $software['logo'] ?? '',
            'structured'      => [$structured, Seo::breadcrumb([
                ['name' => 'Home', 'url' => base_url('/')],
                ['name' => 'Software', 'url' => base_url('/software')],
                ['name' => $software['name'], 'url' => base_url('/software/' . $software['slug'])],
            ])],
            'software'        => $software,
            'category'        => $category,
            'trust'           => \App\Services\TrustScore::explain($software),
            'verifyEvents'    => \App\Services\VerificationLog::recent($id, 12),
            'versions'        => Software::versions($id),
            'features'        => Software::features($id, 'feature'),
            'pros'            => Software::features($id, 'pro'),
            'cons'            => Software::features($id, 'con'),
            'screenshots'     => Software::screenshots($id),
            'tags'            => \App\Core\Database::all(
                'SELECT t.name, t.slug FROM tags t JOIN software_tags st ON st.tag_id = t.id
                 WHERE st.software_id = :id ORDER BY t.name', ['id' => $id]),
            'alternatives'    => Software::alternatives($id, 6),
            'similar'         => Software::similar($software, 6),
        ]);
    }
}
