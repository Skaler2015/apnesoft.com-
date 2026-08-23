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

    /** POST /software-finder — returns ranked matches (JSON). */
    public function match(array $args = []): never
    {
        \App\Core\Csrf::check($this->request);

        $need    = $this->request->str('need');        // category slug or free text
        $osSlug  = $this->request->str('os');
        $ram     = $this->request->int('ram');          // MB
        $freeOnly = $this->request->str('free') === '1';
        $openOnly = $this->request->str('open_source') === '1';
        $level   = $this->request->str('level');        // beginner|professional

        $where = ['s.status = "published"'];
        $params = [];
        $joins = '';

        $categoryId = (int) (Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $need]) ?: 0);
        if ($categoryId) {
            $where[] = '(s.category_id = :cat OR s.subcategory_id = :cat2)';
            $params['cat'] = $categoryId;
            $params['cat2'] = $categoryId;
        } elseif ($need !== '') {
            $where[] = '(s.name LIKE :need OR s.short_description LIKE :need2)';
            $params['need'] = '%' . $need . '%';
            $params['need2'] = '%' . $need . '%';
        }
        if ($osSlug !== '') {
            $joins .= ' JOIN software_operating_systems sos ON sos.software_id = s.id
                        JOIN operating_systems o ON o.id = sos.os_id AND o.slug = :os';
            $params['os'] = $osSlug;
        }
        if ($freeOnly) {
            $where[] = 's.price_type IN ("free","open_source","freemium")';
        }
        if ($openOnly) {
            $where[] = 's.is_open_source = 1';
        }
        if ($ram > 0) {
            $where[] = '(s.min_ram_mb IS NULL OR s.min_ram_mb <= :ram)';
            $params['ram'] = $ram;
        }

        $sql = 'SELECT s.* FROM software s ' . $joins . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 60';
        $candidates = Database::all($sql, $params);

        // Transparent scoring.
        $scored = [];
        foreach ($candidates as $c) {
            $score = 0;
            $reasons = [];
            if ($categoryId && ((int) $c['category_id'] === $categoryId || (int) $c['subcategory_id'] === $categoryId)) {
                $score += 40; $reasons[] = 'Matches your need';
            }
            if ($ram > 0 && $c['min_ram_mb'] !== null && (int) $c['min_ram_mb'] <= $ram) {
                $score += 20; $reasons[] = 'Runs within your RAM';
            }
            if ($freeOnly && in_array($c['price_type'], ['free', 'open_source', 'freemium'], true)) {
                $score += 15; $reasons[] = 'Free';
            }
            if ($openOnly && (int) $c['is_open_source'] === 1) {
                $score += 10; $reasons[] = 'Open source';
            }
            $score += min(15, (int) $c['trust_score'] / 7);
            $c['match_score'] = (int) round($score);
            $c['match_reasons'] = $reasons;
            $scored[] = $c;
        }
        usort($scored, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        $scored = array_slice($scored, 0, 12);

        $this->json(['count' => count($scored), 'matches' => $scored]);
    }
}
