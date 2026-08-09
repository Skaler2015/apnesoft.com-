<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Software;

final class SearchController extends Controller
{
    /** GET /search?q= */
    public function index(array $args = []): never
    {
        $q = $this->request->str('q');
        $results = $q !== '' ? Software::search($q, 40) : [];

        if ($q !== '') {
            try {
                Database::insert('search_queries', ['query' => mb_substr($q, 0, 200), 'results' => count($results)]);
            } catch (\Throwable $e) {
            }
        }

        // Group results by category for a categorized view.
        $grouped = [];
        foreach ($results as $r) {
            $key = $r['operating_system'] ?: 'Other';
            $grouped[$key][] = $r;
        }

        $this->trackView('/search');
        $this->view('pages/search', [
            'title'           => $q !== '' ? "Search: {$q}" : 'Search Software',
            'metaDescription' => 'Search trusted software by name, category, developer or need.',
            'noindex'         => true,
            'query'           => $q,
            'results'         => $results,
            'grouped'         => $grouped,
        ]);
    }

    /** GET /api/suggest?q= — lightweight JSON autocomplete */
    public function suggest(array $args = []): never
    {
        $q = $this->request->str('q');
        if (mb_strlen($q) < 2) {
            $this->json(['results' => []]);
        }
        $rows = Database::all(
            'SELECT name, slug, logo FROM software WHERE status = "published" AND name LIKE :q
             ORDER BY views DESC LIMIT 8',
            ['q' => $q . '%']
        );
        $this->json(['results' => $rows]);
    }
}
