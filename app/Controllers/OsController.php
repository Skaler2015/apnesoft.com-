<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Software;

final class OsController extends Controller
{
    /** GET /os/{slug} */
    public function show(array $args): never
    {
        $slug = $args['slug'] ?? '';
        $os = Database::first('SELECT * FROM operating_systems WHERE slug = :s', ['s' => $slug]);
        if ($os === null) {
            (new ErrorController($this->request))->notFound();
        }

        $page = $this->page();
        $result = Software::filter(['os_slug' => $slug, 'sort' => $this->request->str('sort', 'popular')], $page, 24);

        $this->trackView('/os/' . $slug);
        $this->view('pages/os', [
            'title'           => $os['name'] . ' Software',
            'metaDescription' => 'Best software for ' . $os['name'] . ' — discover, compare and download from official sources.',
            'canonical'       => base_url('/os/' . $slug),
            'os'              => $os,
            'items'           => $result['items'],
            'total'           => $result['total'],
            'page'            => $page,
            'perPage'         => 24,
            'baseUrl'         => '/os/' . $slug,
        ]);
    }
}
