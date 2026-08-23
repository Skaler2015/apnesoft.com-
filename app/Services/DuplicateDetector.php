<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Detects likely duplicates before insert. High-confidence exact matches are
 * treated as the same record; ambiguous ones are queued for admin review.
 */
final class DuplicateDetector
{
    /**
     * @return array{match:?array, confidence:int, reason:string}
     */
    public static function check(array $data): array
    {
        // 1. External reference exact match (github owner/repo, winget id) — highest confidence.
        if (!empty($data['external_ref'])) {
            $row = Database::first('SELECT * FROM software WHERE external_ref = :r LIMIT 1', ['r' => $data['external_ref']]);
            if ($row) {
                return ['match' => $row, 'confidence' => 100, 'reason' => 'Same external reference'];
            }
        }

        // 2. Same official download URL / website host.
        foreach (['official_download_url', 'official_website', 'source_url'] as $field) {
            if (!empty($data[$field])) {
                $host = self::host($data[$field]);
                if ($host !== '') {
                    $row = Database::first(
                        'SELECT * FROM software WHERE official_website LIKE :h OR official_download_url LIKE :h2 LIMIT 1',
                        ['h' => '%' . $host . '%', 'h2' => '%' . $host . '%']
                    );
                    if ($row && self::nameSimilarity($data['name'] ?? '', $row['name']) >= 0.6) {
                        return ['match' => $row, 'confidence' => 92, 'reason' => 'Same official host + similar name'];
                    }
                }
            }
        }

        // 3. Developer + fuzzy name.
        if (!empty($data['developer_name'])) {
            $candidates = Database::all(
                'SELECT * FROM software WHERE developer_name = :d LIMIT 25',
                ['d' => $data['developer_name']]
            );
            foreach ($candidates as $row) {
                $sim = self::nameSimilarity($data['name'] ?? '', $row['name']);
                if ($sim >= 0.85) {
                    return ['match' => $row, 'confidence' => 90, 'reason' => 'Same developer + near-identical name'];
                }
                if ($sim >= 0.6) {
                    return ['match' => $row, 'confidence' => 65, 'reason' => 'Same developer + similar name'];
                }
            }
        }

        // 4. Fuzzy name only across catalog.
        $slug = \slugify($data['name'] ?? '');
        $bySlug = Database::first('SELECT * FROM software WHERE slug = :s', ['s' => $slug]);
        if ($bySlug) {
            return ['match' => $bySlug, 'confidence' => 88, 'reason' => 'Identical slug'];
        }

        $near = Database::all(
            'SELECT * FROM software WHERE name LIKE :n LIMIT 15',
            ['n' => '%' . self::firstToken($data['name'] ?? '') . '%']
        );
        foreach ($near as $row) {
            $sim = self::nameSimilarity($data['name'] ?? '', $row['name']);
            if ($sim >= 0.8) {
                return ['match' => $row, 'confidence' => 60, 'reason' => 'Similar name'];
            }
        }

        return ['match' => null, 'confidence' => 0, 'reason' => ''];
    }

    private static function nameSimilarity(string $a, string $b): float
    {
        $a = self::canon($a);
        $b = self::canon($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        similar_text($a, $b, $percent);
        // Boost when one is contained in the other (e.g. "vlc" in "vlc media player").
        if (str_contains($a, $b) || str_contains($b, $a)) {
            $percent = max($percent, 80.0);
        }
        return $percent / 100;
    }

    /** Normalise a product name: lowercase, drop common filler words. */
    private static function canon(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/\b(player|media|the|app|application|software|free|edition|pro)\b/', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    private static function firstToken(string $name): string
    {
        $canon = self::canon($name);
        return explode(' ', $canon)[0] ?? $canon;
    }

    private static function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? preg_replace('/^www\./', '', strtolower($host)) : '';
    }
}
