<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Cross-source de-duplication.
 *
 * The catalogue is fed by several sources (curated "popular" list, Chocolatey,
 * Homebrew, Flathub, F-Droid, GitHub). The SAME real application shows up in
 * many of them — e.g. VLC appears as popular:vlc, choco:vlc, brew:vlc,
 * flatpak:org.videolan.vlc — each with a different external_ref, so a naive
 * import publishes it several times.
 *
 * This service gives every record a normalised identity key (dedupe_key) and:
 *   - on import, when an app already exists it ENRICHES that one record with any
 *     details the new source adds (missing fields + the new OS) instead of
 *     creating a duplicate;
 *   - as a maintenance pass, merges and removes duplicates already published.
 */
final class Dedupe
{
    /** Trailing WORDS that mark a platform variant of the same product. */
    private const STRIP_WORDS = ['desktop', 'app', 'for', 'windows', 'macos', 'mac', 'linux', 'android', 'pc', 'edition'];

    /** Scalar fields we fill in from another source when blank on the keeper. */
    private const FILL_FIELDS = [
        'developer_name', 'developer_website', 'official_website', 'official_download_url',
        'short_description', 'long_description', 'version', 'license_type', 'price_type', 'logo',
    ];

    /** Normalise a product name to an identity key (lowercase, alnum, no variant word). */
    public static function key(string $name): string
    {
        // Tokenise on any non-alphanumeric run so trailing variant WORDS can be
        // dropped without mangling names that merely end in those letters
        // (e.g. "WhatsApp" must NOT lose "app").
        $words = preg_split('/[^a-z0-9]+/', strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while (count($words) > 1 && in_array($words[count($words) - 1], self::STRIP_WORDS, true)) {
            array_pop($words);
        }
        return implode('', $words);
    }

    /**
     * Find an already-stored software that represents the same product.
     *
     * @param bool $mergeWithGithub when false (a GitHub import), only matches
     *        NON-github rows — two distinct GitHub repos may share a generic
     *        name legitimately, so we never collapse github-vs-github by name.
     * @return array<string,mixed>|null existing row, or null
     */
    public static function findExisting(string $name, bool $mergeWithGithub = true): ?array
    {
        $key = self::key($name);
        if ($key === '') {
            return null;
        }
        $row = Database::first('SELECT * FROM software WHERE dedupe_key = :k ORDER BY id ASC LIMIT 1', ['k' => $key]);
        if ($row === null) {
            return null;
        }
        if (!$mergeWithGithub && ($row['source_type'] ?? '') === 'github_api') {
            return null;
        }
        return $row;
    }

    /**
     * Merge details from a freshly-fetched DTO into an existing record: fill any
     * blank scalar fields, union the OS label, add the OS mapping, and refresh
     * scores + SEO. Never overwrites data the keeper already has.
     *
     * @param array<string,mixed> $existing the stored row
     * @param array<string,mixed> $dto      normalized import DTO
     */
    public static function enrich(array $existing, array $dto, ?string $osSlug = null, ?string $osLabel = null): void
    {
        $id = (int) $existing['id'];
        $update = [];

        foreach (self::FILL_FIELDS as $f) {
            $incoming = $dto[$f] ?? null;
            if ($incoming !== null && $incoming !== '' && empty($existing[$f])) {
                $update[$f] = $incoming;
            }
        }

        // Union the operating-system label so e.g. "Windows" becomes "Windows, macOS".
        $label = $osLabel ?? ($dto['os_label'] ?? null);
        if ($label) {
            $merged = self::mergeOsLabel((string) ($existing['operating_system'] ?? ''), (string) $label);
            if ($merged !== (string) ($existing['operating_system'] ?? '')) {
                $update['operating_system'] = $merged;
            }
        }

        if ($update !== []) {
            Database::update('software', $update, ['id' => $id]);
        }

        // Add the OS mapping row from this source.
        $slug = $osSlug ?? ($dto['os_slug'] ?? null);
        if ($slug) {
            $osId = Database::scalar('SELECT id FROM operating_systems WHERE slug = :s', ['s' => $slug]);
            if ($osId) {
                try {
                    Database::run('INSERT IGNORE INTO software_operating_systems (software_id, os_id) VALUES (:s, :o)',
                        ['s' => $id, 'o' => (int) $osId]);
                } catch (\Throwable $e) {
                }
            }
        }

        if ($update !== []) {
            Seo::generateForSoftware($id);
        }
    }

    /** Combine two comma-separated OS labels into one de-duplicated list. */
    public static function mergeOsLabel(string $a, string $b): string
    {
        $parts = [];
        foreach (array_merge(explode(',', $a), explode(',', $b)) as $p) {
            $p = trim($p);
            if ($p !== '' && !in_array($p, $parts, true)) {
                $parts[] = $p;
            }
        }
        return implode(', ', $parts);
    }

    /**
     * One-time maintenance: merge and remove software already published more than
     * once. Keeps the best row of each group (published + highest trust + oldest),
     * folds the others' details and OS mappings into it, then deletes them.
     *
     * @return array{groups:int, removed:int, remaining:int}
     */
    public static function deduplicateExisting(int $maxGroups = 400): int|array
    {
        self::ensureSchema();
        self::backfill();

        $maxGroups = max(1, min(2000, $maxGroups));
        $groups = Database::all(
            "SELECT dedupe_key FROM software
             WHERE dedupe_key <> ''
             GROUP BY dedupe_key HAVING COUNT(*) > 1
             ORDER BY COUNT(*) DESC
             LIMIT $maxGroups"
        );

        $removed = 0;
        foreach ($groups as $g) {
            $rows = Database::all(
                "SELECT * FROM software WHERE dedupe_key = :k
                 ORDER BY (status = 'published') DESC, trust_score DESC, id ASC",
                ['k' => $g['dedupe_key']]
            );
            if (count($rows) < 2) {
                continue;
            }
            $keep = array_shift($rows);
            $keepId = (int) $keep['id'];

            foreach ($rows as $dup) {
                $dupId = (int) $dup['id'];

                // Fill the keeper's blanks from this duplicate.
                $update = [];
                foreach (self::FILL_FIELDS as $f) {
                    if (empty($keep[$f]) && !empty($dup[$f])) {
                        $update[$f] = $dup[$f];
                        $keep[$f] = $dup[$f];
                    }
                }
                $label = self::mergeOsLabel((string) ($keep['operating_system'] ?? ''), (string) ($dup['operating_system'] ?? ''));
                if ($label !== (string) ($keep['operating_system'] ?? '')) {
                    $update['operating_system'] = $label;
                    $keep['operating_system'] = $label;
                }
                if ($update !== []) {
                    Database::update('software', $update, ['id' => $keepId]);
                }

                // Carry over OS mappings, then delete the duplicate (FK children cascade).
                try {
                    Database::run(
                        'INSERT IGNORE INTO software_operating_systems (software_id, os_id)
                         SELECT :keep, os_id FROM software_operating_systems WHERE software_id = :dup',
                        ['keep' => $keepId, 'dup' => $dupId]
                    );
                } catch (\Throwable $e) {
                }
                Database::delete('software', ['id' => $dupId]);
                $removed++;
            }

            Seo::generateForSoftware($keepId);
        }

        $remaining = (int) Database::scalar(
            "SELECT COUNT(*) FROM (
                SELECT dedupe_key FROM software WHERE dedupe_key <> ''
                GROUP BY dedupe_key HAVING COUNT(*) > 1
             ) t"
        );

        if ($removed > 0) {
            Sitemap::generateAll();
        }

        return ['groups' => count($groups), 'removed' => $removed, 'remaining' => $remaining];
    }

    /** How many duplicate groups currently exist (for the admin UI). */
    public static function duplicateGroups(): int
    {
        try {
            self::ensureSchema();
            self::backfill();
            return (int) Database::scalar(
                "SELECT COUNT(*) FROM (
                    SELECT dedupe_key FROM software WHERE dedupe_key <> ''
                    GROUP BY dedupe_key HAVING COUNT(*) > 1
                 ) t"
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Add the dedupe_key column + index once, if absent. */
    public static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        $exists = Database::scalar(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'software' AND COLUMN_NAME = 'dedupe_key'"
        );
        if ((int) $exists === 0) {
            try {
                Database::run("ALTER TABLE software ADD COLUMN dedupe_key VARCHAR(160) NOT NULL DEFAULT ''");
                Database::run('ALTER TABLE software ADD INDEX idx_dedupe_key (dedupe_key)');
            } catch (\Throwable $e) {
                // Column/index may already exist or privilege missing — non-fatal.
            }
        }
    }

    /** Populate dedupe_key for any rows that don't have one yet. */
    public static function backfill(int $limit = 5000): void
    {
        $rows = Database::all(
            "SELECT id, name FROM software WHERE dedupe_key = '' LIMIT " . max(1, min(20000, $limit))
        );
        foreach ($rows as $r) {
            Database::update('software', ['dedupe_key' => self::key((string) $r['name'])], ['id' => (int) $r['id']]);
        }
    }
}
