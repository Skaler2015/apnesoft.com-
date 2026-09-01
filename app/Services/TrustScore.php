<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Computes a 0-100 trust score and a separate quality score from verified
 * signals only. Never asserts security/malware guarantees.
 */
final class TrustScore
{
    /**
     * @param array $data normalized software fields
     * @return int 0..100
     */
    public static function compute(array $data): int
    {
        $score = 0;

        // Official developer source present.
        if (!empty($data['official_website'])) {
            $score += 30;
        }
        // Verified GitHub repository.
        if (($data['source_type'] ?? '') === 'github_api' || !empty($data['stars'])) {
            $score += 20;
        }
        // Official/authorized download URL present.
        if (!empty($data['official_download_url'])) {
            $score += 20;
        }
        // Valid HTTPS on the primary URLs.
        $primary = $data['official_download_url'] ?? $data['official_website'] ?? $data['source_url'] ?? '';
        if ($primary !== '' && str_starts_with($primary, 'https://')) {
            $score += 10;
        }
        // Version confirmed (present + parseable).
        if (!empty($data['version'])) {
            $score += 10;
        }
        // Recent successful verification.
        if (!empty($data['last_checked_at'])) {
            $score += 10;
        }

        // Penalty for unknown third-party sources.
        if (($data['source_type'] ?? '') === 'manual' && empty($data['official_website'])) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    /**
     * Transparent breakdown of the trust score — every factor, its weight and
     * whether it is met — so the public page can explain the number honestly.
     *
     * @return array{score:int, level:string, factors:array<int,array{label:string,points:int,met:bool}>}
     */
    public static function explain(array $data): array
    {
        $primary = $data['official_download_url'] ?? $data['official_website'] ?? $data['source_url'] ?? '';
        $factors = [
            ['label' => 'Official developer website',   'points' => 30, 'met' => !empty($data['official_website'])],
            ['label' => 'Verified source / repository', 'points' => 20, 'met' => (($data['source_type'] ?? '') === 'github_api' || !empty($data['stars']))],
            ['label' => 'Official / authorized download link', 'points' => 20, 'met' => !empty($data['official_download_url'])],
            ['label' => 'Secure HTTPS link',            'points' => 10, 'met' => ($primary !== '' && str_starts_with((string) $primary, 'https://'))],
            ['label' => 'Version confirmed',            'points' => 10, 'met' => !empty($data['version'])],
            ['label' => 'Recently verified',            'points' => 10, 'met' => !empty($data['last_checked_at'])],
        ];
        $score = isset($data['trust_score']) ? (int) $data['trust_score'] : self::compute($data);
        return ['score' => $score, 'level' => self::level($score), 'factors' => $factors];
    }

    /** Trust level label for a 0–100 score. */
    public static function level(int $score): string
    {
        if ($score >= 90) return 'Highly Trusted';
        if ($score >= 70) return 'Verified';
        if ($score >= 40) return 'Limited Verification';
        return 'Needs Review';
    }

    /**
     * Quality score: metadata completeness + freshness + link status.
     */
    public static function quality(array $data): int
    {
        $score = 0;
        $fields = ['name', 'developer_name', 'short_description', 'long_description', 'version',
                   'license_type', 'category_id', 'operating_system'];
        $present = 0;
        foreach ($fields as $f) {
            if (!empty($data[$f])) {
                $present++;
            }
        }
        $score += (int) round(($present / count($fields)) * 50);

        if (!empty($data['last_updated'])) {
            $ts = strtotime((string) $data['last_updated']);
            if ($ts !== false) {
                $days = (time() - $ts) / 86400;
                $score += $days < 90 ? 25 : ($days < 365 ? 15 : 5);
            }
        }
        if (!empty($data['official_download_url'])) {
            $score += 15;
        }
        if (!empty($data['screenshots']) || !empty($data['features'])) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }
}
