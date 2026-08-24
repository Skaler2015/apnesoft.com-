<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Category;

/**
 * Interactive Software Finder. GET renders the wizard; POST returns ranked
 * matches using a transparent scoring model.
 */
final class FinderController extends Controller
{
    public function index(array $args = []): never
    {
        $this->trackView('/software-finder');
        $this->view('pages/finder', [
            'title'           => 'Software Finder',
            'metaDescription' => 'Answer a few questions and get the best software matches for your PC, budget and needs.',
            'categories'      => Category::roots(),
            'operatingSystems' => Database::all('SELECT * FROM operating_systems ORDER BY sort_order'),
        ]);
    }

    /** POST /software-finder — returns ranked matches (JSON) via the engine. */
    public function match(array $args = []): never
    {
        \App\Core\Csrf::check($this->request);

        // Budget: explicit choice, or the legacy free/open checkboxes.
        $budget = $this->request->str('budget');
        if ($budget === '') {
            if ($this->request->str('open_source') === '1') {
                $budget = 'open_source';
            } elseif ($this->request->str('free') === '1') {
                $budget = 'free';
            }
        }

        $result = \App\Services\Recommendation\RecommendationEngine::recommend([
            'need'   => $this->request->str('need'),
            'os'     => $this->request->str('os'),
            'ram'    => $this->request->int('ram'),
            'budget' => $budget,
            'level'  => $this->request->str('level'),
            'prefs'  => array_map('strval', (array) $this->request->input('prefs', [])),
            'limit'  => 12,
        ]);

        $this->json([
            'count'   => $result['count'],
            'matches' => array_map(static function (array $m): array {
                return [
                    'slug'              => $m['slug'] ?? '',
                    'name'              => $m['name'] ?? '',
                    'developer_name'    => $m['developer_name'] ?? '',
                    'short_description' => $m['short_description'] ?? '',
                    'logo'              => $m['logo'] ?? '',
                    'price_type'        => $m['price_type'] ?? '',
                    'match_score'       => (int) ($m['match_score'] ?? 0),
                    'match_level'       => $m['match_level'] ?? '',
                    'compatibility'     => (int) ($m['compatibility'] ?? 0),
                    'compat_level'      => $m['compat_level'] ?? '',
                    'match_reasons'     => $m['match_reasons'] ?? [],
                    'cautions'          => $m['cautions'] ?? [],
                ];
            }, $result['matches']),
        ]);
    }
}
