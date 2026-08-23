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

        $platform = \App\Core\SiteContext::label();
        $data = [
            'title'           => trim(($platform ? $platform . ' ' : '') . 'Software Downloads') . ' — ' . setting('site_name'),
            'metaDescription' => 'Download & discover the best ' . ($platform ?: '') . ' software, apps and games — from official sources, with version tracking.',
            'latest'          => Software::newest(10),
            'popular'         => Software::popular(10),
            'updates'         => Software::recentUpdates(10),
            'platform'        => $platform,
        ];

        // Category directory — every category that has software (on this platform),
        // each with its top apps, rendered FileHorse-style.
        $sections = [];
        foreach (Category::withCounts() as $c) {
            $items = Software::byCategory((int) $c['id'], 5);
            if ($items) {
                $sections[] = ['category' => $c, 'items' => $items];
            }
        }
        $data['categorySections'] = $sections;

        $this->view('home/index', $data);
    }
}
