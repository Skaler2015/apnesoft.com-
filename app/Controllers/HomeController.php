<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Software;

final class HomeController extends Controller
{
    public function index(array $args = []): never
    {
        $this->trackView('/');

        $data = [
            'title'           => setting('site_name') . ' — ' . setting('tagline'),
            'metaDescription' => 'Discover, compare and download trusted software from official sources. Version tracking, alternatives and a smart software finder.',
            'popular'         => Software::popular(8),
            'recentlyUpdated' => Software::recentlyUpdated(8),
            'newest'          => Software::newest(8),
            'free'            => Software::byPrice('free', 8),
            'openSource'      => Software::openSource(8),
            'windows'         => Software::byOsSlug('windows', 8),
            'macos'           => Software::byOsSlug('macos', 8),
            'linux'           => Software::byOsSlug('linux', 8),
            'lowEnd'          => Software::lowEndPc(4096, 8),
            'trending'        => Category::trending(10),
            'updates'         => Software::recentUpdates(10),
        ];

        // "Popular categories" — each top category with its top apps.
        $sections = [];
        foreach (Category::trending(6) as $c) {
            $items = Software::byCategory((int) $c['id'], 5);
            if (count($items) >= 3) {
                $sections[] = ['category' => $c, 'items' => $items];
            }
        }
        $data['categorySections'] = $sections;

        $this->view('home/index', $data);
    }
}
