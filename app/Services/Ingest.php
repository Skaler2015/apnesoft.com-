<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;
use App\Models\Notification;
use App\Support\Version;

/**
 * The heart of the discovery pipeline. Takes a normalized software DTO from any
 * source and: detects duplicates, detects version bumps, scores trust/quality,
 * applies auto-publish rules, writes the record, and generates SEO metadata.
 *
 * Returns a small result describing what happened, for crawler logs.
 */
final class Ingest
{
    /** Fields the DTO may contain. */
    private const CORE = [
        'name', 'developer_name', 'developer_website', 'official_website',
        'official_download_url', 'short_description', 'long_description', 'version',
        'release_date', 'license_type', 'price_type', 'is_open_source', 'file_size',
        'architecture', 'operating_system', 'min_ram_mb', 'minimum_requirements',
        'category_id', 'subcategory_id', 'logo', 'changelog', 'stars', 'forks',
        'source_id', 'source_type', 'source_url', 'external_ref',
    ];

    /**
     * @return array{action:string, software_id:?int, message:string}
     */
    public static function process(array $dto): array
    {
        $dto['name'] = trim((string) ($dto['name'] ?? ''));
        if ($dto['name'] === '') {
            return ['action' => 'skipped', 'software_id' => null, 'message' => 'missing name'];
        }
        $dto['last_checked_at'] = gmdate('Y-m-d H:i:s');

        // --- Duplicate detection --------------------------------------------
        $dup = DuplicateDetector::check($dto);

        if ($dup['match'] !== null && $dup['confidence'] >= 88) {
            // Treat as the same software: update / detect version bump.
            return self::updateExisting($dup['match'], $dto);
        }

        if ($dup['match'] !== null && $dup['confidence'] >= 55) {
            // Ambiguous — queue for admin review, do not merge automatically.
            self::queueDuplicate($dup, $dto);
            // Still allow creation as a review-pending record so nothing is lost.
        }

        return self::createNew($dto);
    }

    private static function createNew(array $dto): array
    {
        $trust = TrustScore::compute($dto);
        $quality = TrustScore::quality($dto);
        $decision = self::publishDecision($dto, $trust);

        $slug = self::uniqueSlug(\slugify($dto['name']));

        $insert = self::pick($dto, self::CORE);
        $insert['slug'] = $slug;
        $insert['trust_score'] = $trust;
        $insert['quality_score'] = $quality;
        $insert['verification_status'] = self::verificationStatus($trust);
        $insert['status'] = $decision['status'];
        $insert['auto_publish'] = $decision['auto'] ? 1 : 0;
        $insert['discovered_at'] = gmdate('Y-m-d H:i:s');
        $insert['last_checked_at'] = $dto['last_checked_at'];
        $insert['last_updated'] = $dto['release_date'] ?? gmdate('Y-m-d H:i:s');

        $id = Database::insert('software', array_filter($insert, static fn($v) => $v !== null));

        // Initial version row.
        if (!empty($dto['version'])) {
            self::addVersion($id, $dto, true);
        }

        Seo::generateForSoftware($id);

        Notification::push('new_software', 'New software discovered: ' . $dto['name'],
            "Trust {$trust}, status {$decision['status']}", 'info', 'software', $id);

        return [
            'action' => $decision['status'] === 'published' ? 'published' : 'review',
            'software_id' => $id,
            'message' => "created ({$decision['status']}, trust $trust)",
        ];
    }

    private static function updateExisting(array $existing, array $dto): array
    {
        $id = (int) $existing['id'];
        $changed = [];

        // --- Version detection: only bump on a genuinely newer version -------
        $newVersion = trim((string) ($dto['version'] ?? ''));
        $curVersion = trim((string) ($existing['version'] ?? ''));

        if ($newVersion !== '' && Version::isNewer($newVersion, $curVersion)) {
            $changed['previous_version'] = $curVersion;
            $changed['version'] = $newVersion;
            $changed['release_date'] = $dto['release_date'] ?? $existing['release_date'];
            $changed['last_updated'] = gmdate('Y-m-d H:i:s');
            if (!empty($dto['changelog'])) {
                $changed['changelog'] = $dto['changelog'];
            }
            if (!empty($dto['official_download_url'])) {
                $changed['official_download_url'] = $dto['official_download_url'];
            }

            self::addVersion($id, $dto, true);

            Database::insert('update_history', [
                'software_id' => $id,
                'old_version' => $curVersion,
                'new_version' => $newVersion,
                'release_date' => $dto['release_date'] ?? null,
                'source_id'   => $dto['source_id'] ?? null,
                'detail'      => 'Auto-detected via ' . ($dto['source_type'] ?? 'source'),
            ]);

            Notification::push('new_version', "New version {$newVersion}: {$existing['name']}",
                "Was {$curVersion}", 'success', 'software', $id);
        }

        // Refresh light metadata if previously empty.
        foreach (['developer_name', 'official_website', 'official_download_url', 'short_description',
                  'long_description', 'license_type', 'stars', 'forks', 'logo'] as $f) {
            if (empty($existing[$f]) && !empty($dto[$f])) {
                $changed[$f] = $dto[$f];
            }
        }

        $merged = array_merge($existing, $changed);
        $changed['trust_score'] = TrustScore::compute($merged);
        $changed['quality_score'] = TrustScore::quality($merged);
        $changed['verification_status'] = self::verificationStatus($changed['trust_score']);
        $changed['last_checked_at'] = gmdate('Y-m-d H:i:s');

        Database::update('software', $changed, ['id' => $id]);
        Seo::generateForSoftware($id);

        $action = isset($changed['version']) ? 'updated' : 'refreshed';
        return ['action' => $action, 'software_id' => $id, 'message' => $action];
    }

    private static function addVersion(int $softwareId, array $dto, bool $current): void
    {
        $version = trim((string) ($dto['version'] ?? ''));
        if ($version === '') {
            return;
        }
        try {
            if ($current) {
                Database::run('UPDATE software_versions SET is_current = 0 WHERE software_id = :id', ['id' => $softwareId]);
            }
            Database::run(
                'INSERT INTO software_versions (software_id, version, normalized, release_date, download_url, file_size, changelog, is_current, source_id)
                 VALUES (:sid, :v, :n, :rd, :du, :fs, :cl, :cur, :src)
                 ON DUPLICATE KEY UPDATE is_current = VALUES(is_current), download_url = VALUES(download_url),
                                         changelog = VALUES(changelog), release_date = VALUES(release_date)',
                [
                    'sid' => $softwareId,
                    'v'   => $version,
                    'n'   => Version::normalize($version),
                    'rd'  => $dto['release_date'] ?? null,
                    'du'  => $dto['official_download_url'] ?? null,
                    'fs'  => $dto['file_size'] ?? null,
                    'cl'  => $dto['changelog'] ?? null,
                    'cur' => $current ? 1 : 0,
                    'src' => $dto['source_id'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // ignore duplicate version race
        }
    }

    /**
     * Auto-publish rules (thresholds configurable via Settings).
     * @return array{status:string, auto:bool}
     */
    private static function publishDecision(array $dto, int $trust): array
    {
        $autoT   = Settings::int('threshold_auto_publish', 90);
        $condT   = Settings::int('threshold_conditional', 70);
        $reviewT = Settings::int('threshold_review', 40);

        $mandatoryOk = self::mandatoryFieldsPass($dto);

        if ($trust >= $autoT && $mandatoryOk) {
            return ['status' => 'published', 'auto' => true];
        }
        if ($trust >= $condT && $mandatoryOk) {
            return ['status' => 'published', 'auto' => true];
        }
        if ($trust >= $reviewT) {
            return ['status' => 'review', 'auto' => false];
        }
        return ['status' => 'rejected', 'auto' => false];
    }

    private static function mandatoryFieldsPass(array $dto): bool
    {
        $sourceUrl = $dto['official_download_url'] ?? $dto['official_website'] ?? $dto['source_url'] ?? '';
        return !empty($dto['name'])
            && !empty($dto['developer_name'])
            && !empty($dto['version'])
            && !empty($dto['operating_system'])
            && !empty($dto['category_id'])
            && $sourceUrl !== ''
            && filter_var($sourceUrl, FILTER_VALIDATE_URL) !== false;
    }

    private static function verificationStatus(int $trust): string
    {
        if ($trust >= 70) return 'verified';
        if ($trust >= 40) return 'review';
        return 'unverified';
    }

    private static function queueDuplicate(array $dup, array $dto): void
    {
        Database::insert('duplicate_candidates', [
            'software_id' => $dup['match']['id'],
            'match_id'    => $dup['match']['id'],
            'confidence'  => $dup['confidence'],
            'reason'      => $dup['reason'] . ' vs "' . ($dto['name'] ?? '') . '"',
        ]);
        Notification::push('duplicate', 'Possible duplicate: ' . ($dto['name'] ?? ''),
            $dup['reason'] . " (confidence {$dup['confidence']})", 'warning', 'software', (int) $dup['match']['id']);
    }

    private static function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Database::scalar('SELECT id FROM software WHERE slug = :s', ['s' => $slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private static function pick(array $src, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $src)) {
                $out[$k] = $src[$k];
            }
        }
        return $out;
    }
}
