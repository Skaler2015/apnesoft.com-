<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;

/**
 * Serves sitemap.xml, sitemap segments, and robots.txt. Sitemaps are generated
 * to disk by cron; this controller falls back to serving fresh output.
 */
final class SystemController extends Controller
{
    public function sitemap(array $args = []): never
    {
        $file = Config::get('paths.public') . '/sitemap.xml';
        if (is_file($file)) {
            $this->xml((string) file_get_contents($file));
        }
        // Generate on demand if missing.
        \App\Services\Sitemap::generateAll();
        $this->xml(is_file($file) ? (string) file_get_contents($file) : $this->emptyUrlset());
    }

    public function sitemapSegment(array $args): never
    {
        $seg = preg_replace('/[^a-z]/', '', $args['name'] ?? '');
        $file = Config::get('paths.public') . '/sitemaps/' . $seg . '.xml';
        if (is_file($file)) {
            $this->xml((string) file_get_contents($file));
        }
        $this->xml($this->emptyUrlset());
    }

    public function robots(array $args = []): never
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /download/',
            'Disallow: /search',
            'Disallow: /compare?',
            'Allow: /',
            '',
            'Sitemap: ' . base_url('/sitemap.xml'),
        ];
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", $lines);
        exit;
    }

    private function xml(string $body): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        echo $body;
        exit;
    }

    private function emptyUrlset(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }
}
