<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Software;

/**
 * Standalone content pages: new software, software updates, low-end PC.
 */
final class PageController extends Controller
{
    /** GET /new-software */
    public function newSoftware(array $args = []): never
    {
        $page = $this->page();
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $items = Database::all(
            'SELECT * FROM software WHERE status = "published"
             ORDER BY COALESCE(discovered_at, created_at) DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $total = (int) Database::scalar('SELECT COUNT(*) FROM software WHERE status = "published"');

        $this->trackView('/new-software');
        $this->view('pages/new_software', [
            'title'           => 'New Software',
            'metaDescription' => 'Recently discovered and newly added software with developer, category, version and source.',
            'canonical'       => base_url('/new-software'),
            'items'           => $items,
            'total'           => $total,
            'page'            => $page,
            'perPage'         => $perPage,
            'baseUrl'         => '/new-software',
        ]);
    }

    /** GET /software-updates */
    public function updates(array $args = []): never
    {
        $buckets = [
            'today'      => 'DATE(u.created_at) = CURDATE()',
            'yesterday'  => 'DATE(u.created_at) = (CURDATE() - INTERVAL 1 DAY)',
            'this_week'  => 'u.created_at >= (CURDATE() - INTERVAL 7 DAY) AND DATE(u.created_at) < (CURDATE() - INTERVAL 1 DAY)',
            'this_month' => 'u.created_at >= (CURDATE() - INTERVAL 1 MONTH) AND u.created_at < (CURDATE() - INTERVAL 7 DAY)',
        ];
        $grouped = [];
        foreach ($buckets as $key => $cond) {
            $grouped[$key] = Database::all(
                "SELECT u.*, s.name, s.slug, s.logo FROM update_history u
                 JOIN software s ON s.id = u.software_id
                 WHERE s.status = 'published' AND $cond
                 ORDER BY u.created_at DESC LIMIT 100"
            );
        }

        $this->trackView('/software-updates');
        $this->view('pages/updates', [
            'title'           => 'Software Updates',
            'metaDescription' => 'Latest software version updates — see what changed and when, from official sources.',
            'canonical'       => base_url('/software-updates'),
            'grouped'         => $grouped,
        ]);
    }

    /** GET /low-end-pc */
    public function lowEndPc(array $args = []): never
    {
        $maxRam = $this->request->int('max_ram', 4096) ?: 4096;
        $result = Software::filter(['max_ram' => $maxRam, 'sort' => 'popular'], $this->page(), 24);

        $this->trackView('/low-end-pc');
        $this->view('pages/low_end_pc', [
            'title'           => 'Best Software for Low-End PCs',
            'metaDescription' => 'Lightweight software that runs well on low-end PCs — filter by available RAM and storage type.',
            'canonical'       => base_url('/low-end-pc'),
            'items'           => $result['items'],
            'total'           => $result['total'],
            'page'            => $this->page(),
            'perPage'         => 24,
            'maxRam'          => $maxRam,
            'baseUrl'         => '/low-end-pc',
        ]);
    }
}
