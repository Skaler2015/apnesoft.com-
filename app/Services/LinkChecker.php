<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Notification;
use App\Support\Http;

/**
 * Verifies download / official URLs and records status. Redirects are NOT
 * treated as errors. Rate-limited by processing a bounded batch per run.
 */
final class LinkChecker
{
    public static function run(int $batch = 40): array
    {
        $stats = ['processed' => 0, 'working' => 0, 'redirect' => 0, 'unavailable' => 0, 'broken' => 0];

        $rows = Database::all(
            'SELECT id, name, official_download_url, official_website FROM software
             WHERE status = "published"
               AND (official_download_url IS NOT NULL OR official_website IS NOT NULL)
             ORDER BY last_checked_at ASC LIMIT ' . (int) $batch
        );

        foreach ($rows as $row) {
            $url = $row['official_download_url'] ?: $row['official_website'];
            if (!$url) {
                continue;
            }
            $stats['processed']++;
            $result = self::checkUrl($url);
            $stats[$result['result']] = ($stats[$result['result']] ?? 0) + 1;

            Database::insert('verification_logs', [
                'software_id' => $row['id'],
                'check_type'  => 'link',
                'url'         => $url,
                'http_status' => $result['status'],
                'result'      => $result['result'],
                'detail'      => $result['detail'],
            ]);

            Database::update('software', ['last_checked_at' => gmdate('Y-m-d H:i:s')], ['id' => $row['id']]);

            if ($result['result'] === 'broken') {
                Notification::push('broken_link', 'Broken download link: ' . $row['name'],
                    "$url returned {$result['status']}", 'error', 'software', (int) $row['id']);
            }
        }

        return $stats;
    }

    /** @return array{status:int, result:string, detail:string} */
    public static function checkUrl(string $url): array
    {
        // HEAD first; some servers reject HEAD -> retry GET.
        $resp = Http::head($url);
        if ($resp['status'] === 0 || $resp['status'] === 405 || $resp['status'] === 403) {
            $resp = Http::get($url, [], 15);
        }
        $status = $resp['status'];
        $redirected = $resp['effective_url'] !== $url;

        $result = match (true) {
            $status >= 200 && $status < 300 => $redirected ? 'redirect' : 'working',
            $status >= 300 && $status < 400 => 'redirect',
            $status === 429 || $status === 503 || $status === 408 => 'unavailable',
            $status === 0 => 'unavailable',
            default => 'broken',
        };

        return [
            'status' => $status,
            'result' => $result,
            'detail' => $resp['error'] ?? ($redirected ? 'redirected to ' . $resp['effective_url'] : ''),
        ];
    }
}
