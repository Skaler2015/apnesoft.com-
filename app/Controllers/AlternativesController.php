<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Software;

final class AlternativesController extends Controller
{
    /** GET /alternatives/{slug} */
    public function show(array $args): never
    {
        $software = Software::findPublishedBySlug($args['slug'] ?? '');
        if ($software === null) {
            (new ErrorController($this->request))->notFound();
        }
        $id = (int) $software['id'];

        $alternatives = Software::alternatives($id, 12);
        // Fall back to same-category if no curated alternatives, but only build a
        // meaningful page when we actually have results.
        if (empty($alternatives)) {
            $alternatives = Software::similar($software, 12);
        }
        $noindex = count($alternatives) < 2;

        $this->trackView('/alternatives/' . $software['slug'], 'software', $id);
        $this->view('pages/alternatives', [
            'title'           => 'Best ' . $software['name'] . ' Alternatives',
            'metaDescription' => 'Top alternatives to ' . $software['name'] . ' by category, features and platform.',
            'canonical'       => base_url('/alternatives/' . $software['slug']),
            'noindex'         => $noindex,
            'software'        => $software,
            'alternatives'    => $alternatives,
        ]);
    }
}
