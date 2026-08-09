<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Software;

/**
 * Comparison engine. /compare?ids= builds a comparison; /compare/{slug}
 * renders a stored/derived comparison. Pages are only indexable when they
 * contain 2-4 real software records.
 */
final class CompareController extends Controller
{
    /** GET /compare — picker + optional ?ids=1,2,3 */
    public function index(array $args = []): never
    {
        $idsRaw = $this->request->str('ids');
        $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
        if (count($ids) >= 2) {
            $slug = $this->buildSlug($ids);
            if ($slug !== null) {
                $this->redirect(base_url('/compare/' . $slug));
            }
        }

        $this->trackView('/compare');
        $this->view('pages/compare_picker', [
            'title'           => 'Compare Software',
            'metaDescription' => 'Compare software side by side — license, OS, version, requirements and features.',
            'popular'         => Software::popular(24),
        ]);
    }

    /** GET /compare/{slug} — vlc-vs-potplayer */
    public function show(array $args): never
    {
        $slug = $args['slug'] ?? '';
        $names = array_filter(explode('-vs-', $slug));
        $items = [];
        foreach ($names as $name) {
            $s = Software::findPublishedBySlug(trim($name));
            if ($s) {
                $items[] = $s;
            }
        }

        if (count($items) < 2) {
            (new ErrorController($this->request))->notFound();
        }
        $items = array_slice($items, 0, 4);

        // Record / update the comparison for sitemap eligibility.
        $indexable = count($items) >= 2;
        $this->recordComparison($slug, $items, $indexable);

        $this->trackView('/compare/' . $slug);
        $this->view('pages/compare', [
            'title'           => implode(' vs ', array_column($items, 'name')) . ' — Comparison',
            'metaDescription' => 'Side-by-side comparison of ' . implode(', ', array_column($items, 'name')) . '.',
            'canonical'       => base_url('/compare/' . $slug),
            'noindex'         => !$indexable,
            'items'           => $items,
            'attributes'      => $this->attributes(),
        ]);
    }

    private function attributes(): array
    {
        return [
            'developer_name'   => 'Developer',
            'version'          => 'Latest Version',
            'license_type'     => 'License',
            'price_type'       => 'Price',
            'operating_system' => 'Operating System',
            'architecture'     => 'Architecture',
            'file_size'        => 'File Size',
            'min_ram_mb'       => 'Min RAM',
            'is_open_source'   => 'Open Source',
            'last_updated'     => 'Last Updated',
            'trust_score'      => 'Trust Score',
        ];
    }

    private function buildSlug(array $ids): ?string
    {
        $slugs = [];
        foreach (array_slice($ids, 0, 4) as $id) {
            $s = Software::find($id);
            if ($s && $s['status'] === 'published') {
                $slugs[] = $s['slug'];
            }
        }
        return count($slugs) >= 2 ? implode('-vs-', $slugs) : null;
    }

    private function recordComparison(string $slug, array $items, bool $indexable): void
    {
        try {
            Database::run(
                'INSERT INTO software_comparisons (slug, software_ids, title, is_indexable, views)
                 VALUES (:slug, :ids, :title, :idx, 1)
                 ON DUPLICATE KEY UPDATE views = views + 1, is_indexable = VALUES(is_indexable), updated_at = NOW()',
                [
                    'slug'  => $slug,
                    'ids'   => implode(',', array_column($items, 'id')),
                    'title' => implode(' vs ', array_column($items, 'name')),
                    'idx'   => $indexable ? 1 : 0,
                ]
            );
        } catch (\Throwable $e) {
        }
    }
}
