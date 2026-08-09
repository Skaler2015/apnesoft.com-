<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Response;
use App\Services\GitHubService;
use App\Services\Ingest;

/**
 * GitHub release webhook receiver. Validates the HMAC signature before doing
 * anything. Never trusts arbitrary incoming requests.
 */
final class WebhookController extends Controller
{
    /** POST /webhooks/github */
    public function github(array $args = []): never
    {
        $secret = (string) Config::get('integrations.github_webhook_secret', '');
        $payload = $this->request->rawBody();
        $signature = $this->request->header('X-Hub-Signature-256');

        if ($secret === '' || $signature === null || !$this->validSignature($payload, $secret, $signature)) {
            Logger::warn('Rejected GitHub webhook: invalid signature');
            Response::json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $event = $this->request->header('X-GitHub-Event');
        if ($event !== 'release') {
            Response::json(['ok' => true, 'ignored' => $event]);
        }

        $data = json_decode($payload, true);
        if (($data['action'] ?? '') !== 'published' && ($data['action'] ?? '') !== 'released') {
            Response::json(['ok' => true, 'ignored_action' => $data['action'] ?? '']);
        }

        $repo = $data['repository']['full_name'] ?? '';
        if ($repo === '') {
            Response::json(['ok' => false, 'error' => 'no repo'], 422);
        }

        $ref = 'github:' . strtolower($repo);
        $existing = Database::first('SELECT * FROM software WHERE external_ref = :r', ['r' => $ref]);

        // Build a DTO from the release payload and process it.
        $release = $data['release'] ?? [];
        $dto = [
            'name'                  => $existing['name'] ?? ($data['repository']['name'] ?? $repo),
            'developer_name'        => $data['repository']['owner']['login'] ?? '',
            'official_website'      => $data['repository']['homepage'] ?: ($data['repository']['html_url'] ?? ''),
            'official_download_url' => $this->firstAsset($release) ?: ($release['html_url'] ?? ''),
            'version'               => $release['tag_name'] ?? '',
            'release_date'          => isset($release['published_at']) ? substr($release['published_at'], 0, 10) : null,
            'changelog'             => str_excerpt($release['body'] ?? '', 4000),
            'price_type'            => 'open_source',
            'is_open_source'        => 1,
            'source_type'           => 'github_webhook',
            'source_url'            => $data['repository']['html_url'] ?? '',
            'external_ref'          => $ref,
            'category_id'           => $existing['category_id'] ?? null,
            'operating_system'      => $existing['operating_system'] ?? 'Windows, macOS, Linux',
        ];

        $result = Ingest::process($dto);

        // Refresh sitemap after a confirmed change.
        if (in_array($result['action'], ['updated', 'published'], true)) {
            \App\Services\Sitemap::generateAll();
        }

        Logger::info("GitHub webhook processed for $repo: {$result['action']}");
        Response::json(['ok' => true, 'result' => $result['action']]);
    }

    private function validSignature(string $payload, string $secret, string $signature): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    private function firstAsset(array $release): ?string
    {
        return $release['assets'][0]['browser_download_url'] ?? null;
    }
}
