<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Models\Software;

/**
 * Outbound download redirect. Records the click, then 302s the user to the
 * OFFICIAL destination. Never claims to host the installer ourselves.
 */
final class DownloadController extends Controller
{
    /** GET /download/{slug} */
    public function go(array $args): never
    {
        $software = Software::findPublishedBySlug($args['slug'] ?? '');
        if ($software === null) {
            (new ErrorController($this->request))->notFound();
        }

        $destination = $software['official_download_url'] ?: $software['official_website'] ?: $software['source_url'];
        if (!$destination || filter_var($destination, FILTER_VALIDATE_URL) === false) {
            (new ErrorController($this->request))->notFound();
        }

        // Only allow http(s) outbound.
        $scheme = strtolower((string) parse_url($destination, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            (new ErrorController($this->request))->notFound();
        }

        $source = str_contains((string) $software['source_type'], 'github') ? 'github'
            : (($software['source_type'] === 'winget') ? 'winget' : 'official');

        try {
            Database::insert('software_downloads', [
                'software_id' => $software['id'],
                'source'      => $source,
                'destination' => mb_substr($destination, 0, 700),
                'device'      => $this->request->device(),
                'referrer'    => mb_substr((string) $this->request->header('Referer'), 0, 300),
            ]);
            Database::run('UPDATE software SET download_clicks = download_clicks + 1 WHERE id = :id', ['id' => $software['id']]);
        } catch (\Throwable $e) {
        }

        // Do not pass a Referer to the destination beyond origin (privacy).
        Response::redirect($destination, 302);
    }
}
