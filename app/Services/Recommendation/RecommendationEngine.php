<?php

declare(strict_types=1);

namespace App\Services\Recommendation;

use App\Core\Database;

/**
 * Transparent, rule-based software recommendation engine.
 *
 * It scores each candidate out of 100 across weighted factors and returns a
 * plain-language explanation of *why* each result matches — plus honest
 * "cautions" when data is missing (never pretends to know compatibility).
 *
 *   Category match .......... 30
 *   Platform compatibility .. 20
 *   Hardware compatibility .. 20
 *   Budget match ............ 10
 *   Experience match ........ 10
 *   Feature / preference .... 10
 *   -------------------------------
 *   Total .................. 100
 *
 * Weights are kept in one place so the model can evolve without touching
 * controllers.
 */
final class RecommendationEngine
{
    /** @var array<string,int> */
    public const WEIGHTS = [
        'category'   => 30,
        'platform'   => 20,
        'hardware'   => 20,
        'budget'     => 10,
        'experience' => 10,
        'features'   => 10,
    ];

    private const OS_KEYWORD = [
        'windows' => 'windows', 'macos' => 'mac', 'mac' => 'mac',
        'ios' => 'ios', 'android' => 'android', 'linux' => 'linux',
    ];

    /**
     * @param array{need?:string, os?:string, ram?:int, budget?:string, level?:string, prefs?:string[], limit?:int} $c
     * @return array{count:int, matches:array<int,array<string,mixed>>}
     */
    public static function recommend(array $c): array
    {
        $need   = trim((string) ($c['need'] ?? ''));
        $osSlug = trim((string) ($c['os'] ?? ''));
        $ram    = (int) ($c['ram'] ?? 0);
        $budget = trim((string) ($c['budget'] ?? ''));
        $level  = trim((string) ($c['level'] ?? ''));
        $prefs  = array_values(array_filter(array_map('strval', (array) ($c['prefs'] ?? []))));
        $limit  = max(1, min(24, (int) ($c['limit'] ?? 12)));

        $categoryId = (int) (Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $need]) ?: 0);
        $osKw = self::OS_KEYWORD[strtolower($osSlug)] ?? '';

        // ---- Candidate selection (relevance-based; scoring does the ranking) ----
        $where = ['s.status = "published"'];
        $params = [];
        if ($categoryId) {
            $where[] = '(s.category_id = :cat OR s.subcategory_id = :cat2)';
            $params['cat'] = $categoryId;
            $params['cat2'] = $categoryId;
        } elseif ($need !== '') {
            $where[] = '(s.name LIKE :need OR s.short_description LIKE :need2 OR s.long_description LIKE :need3)';
            $params['need'] = '%' . $need . '%';
            $params['need2'] = '%' . $need . '%';
            $params['need3'] = '%' . $need . '%';
        }
        if ($osKw !== '') {
            $where[] = '(s.operating_system LIKE :os OR s.operating_system IS NULL OR s.operating_system = "")';
            $params['os'] = '%' . $osKw . '%';
        }
        $sql = 'SELECT s.* FROM software s WHERE ' . implode(' AND ', $where)
            . ' ORDER BY s.trust_score DESC LIMIT 100';
        $candidates = Database::all($sql, $params);
        if (!$candidates) {
            return ['count' => 0, 'matches' => []];
        }

        // Tags for all candidates in one query (feature/preference matching).
        $ids = array_map(static fn($r) => (int) $r['id'], $candidates);
        $tagsById = [];
        $ph = [];
        $tp = [];
        foreach ($ids as $i => $id) { $ph[] = ':i' . $i; $tp['i' . $i] = $id; }
        foreach (Database::all(
            "SELECT st.software_id sid, t.name FROM software_tags st JOIN tags t ON t.id = st.tag_id
             WHERE st.software_id IN (" . implode(',', $ph) . ')',
            $tp
        ) as $row) {
            $tagsById[(int) $row['sid']][] = mb_strtolower((string) $row['name']);
        }

        $scored = [];
        foreach ($candidates as $s) {
            $tags = $tagsById[(int) $s['id']] ?? [];
            $scored[] = self::score($s, $tags, [
                'categoryId' => $categoryId, 'need' => $need, 'osSlug' => $osSlug, 'osKw' => $osKw,
                'ram' => $ram, 'budget' => $budget, 'level' => $level, 'prefs' => $prefs,
            ]);
        }
        // Rank by score, then trust as a tiebreaker.
        usort($scored, static fn($a, $b) =>
            ($b['match_score'] <=> $a['match_score']) ?: ((int) $b['trust_score'] <=> (int) $a['trust_score']));

        return ['count' => min($limit, count($scored)), 'matches' => array_slice($scored, 0, $limit)];
    }

    /** Compatibility of a software row against a user's OS + RAM (0–100 + level). */
    public static function compatibility(array $s, string $osSlug, int $ram): array
    {
        $osKw = self::OS_KEYWORD[strtolower($osSlug)] ?? '';
        [$plat] = self::platform($s, $osKw, $osSlug);
        [$hw]   = self::hardware($s, $ram);
        $max = self::WEIGHTS['platform'] + self::WEIGHTS['hardware'];
        $pct = $max ? (int) round(($plat + $hw) / $max * 100) : 0;
        return ['score' => $pct, 'level' => self::level($pct)];
    }

    // -----------------------------------------------------------------------

    /** @return array<string,mixed> the row plus score/level/reasons/cautions */
    private static function score(array $s, array $tags, array $ctx): array
    {
        $reasons = [];
        $cautions = [];
        $pts = 0;

        // Category
        if ($ctx['categoryId'] && ((int) $s['category_id'] === $ctx['categoryId'] || (int) ($s['subcategory_id'] ?? 0) === $ctx['categoryId'])) {
            $pts += self::WEIGHTS['category'];
            $reasons[] = 'Matches your requirement';
        } elseif ($ctx['need'] !== '') {
            $pts += (int) round(self::WEIGHTS['category'] * 0.6);
            $reasons[] = 'Related to your search';
        } else {
            $pts += self::WEIGHTS['category'];
        }

        // Platform
        [$plat, $platReason, $platCaution] = self::platform($s, $ctx['osKw'], $ctx['osSlug']);
        $pts += $plat;
        if ($platReason) { $reasons[] = $platReason; }
        if ($platCaution) { $cautions[] = $platCaution; }

        // Hardware / RAM
        [$hw, $hwReason, $hwCaution] = self::hardware($s, (int) $ctx['ram']);
        $pts += $hw;
        if ($hwReason) { $reasons[] = $hwReason; }
        if ($hwCaution) { $cautions[] = $hwCaution; }

        // Budget
        [$bud, $budReason] = self::budget($s, (string) $ctx['budget']);
        $pts += $bud;
        if ($budReason) { $reasons[] = $budReason; }

        // Experience
        [$exp, $expReason] = self::experience($s, (string) $ctx['level'], $tags);
        $pts += $exp;
        if ($expReason) { $reasons[] = $expReason; }

        // Features / preferences
        [$feat, $featReasons] = self::features($s, $tags, (array) $ctx['prefs']);
        $pts += $feat;
        $reasons = array_merge($reasons, $featReasons);

        $pts = max(0, min(100, (int) round($pts)));
        $compatMax = self::WEIGHTS['platform'] + self::WEIGHTS['hardware'];
        $compat = $compatMax ? (int) round(($plat + $hw) / $compatMax * 100) : 0;

        $s['match_score']   = $pts;
        $s['match_level']   = self::level($pts);
        $s['compatibility'] = $compat;
        $s['compat_level']  = self::level($compat);
        $s['match_reasons'] = array_values(array_unique($reasons));
        $s['cautions']      = array_values(array_unique($cautions));
        return $s;
    }

    /** @return array{0:int,1:?string,2:?string} */
    private static function platform(array $s, string $osKw, string $osSlug): array
    {
        if ($osKw === '') {
            return [self::WEIGHTS['platform'], null, null];
        }
        $os = mb_strtolower((string) ($s['operating_system'] ?? ''));
        if ($os === '') {
            return [(int) round(self::WEIGHTS['platform'] * 0.5), null, 'Platform support not listed'];
        }
        if (str_contains($os, $osKw)) {
            return [self::WEIGHTS['platform'], 'Supports ' . self::osLabel($osSlug), null];
        }
        return [0, null, 'May not support ' . self::osLabel($osSlug)];
    }

    /** @return array{0:int,1:?string,2:?string} */
    private static function hardware(array $s, int $ram): array
    {
        if ($ram <= 0) {
            return [self::WEIGHTS['hardware'], null, null];
        }
        $min = $s['min_ram_mb'] ?? null;
        if ($min === null || $min === '') {
            return [(int) round(self::WEIGHTS['hardware'] * 0.5), null, 'RAM requirement unknown'];
        }
        if ((int) $min <= $ram) {
            return [self::WEIGHTS['hardware'], 'Runs within your ' . self::gb($ram) . ' RAM', null];
        }
        return [0, null, 'Needs more than ' . self::gb($ram) . ' RAM'];
    }

    /** @return array{0:int,1:?string} */
    private static function budget(array $s, string $budget): array
    {
        $price = (string) ($s['price_type'] ?? '');
        $free = in_array($price, ['free', 'open_source', 'freemium'], true);
        switch ($budget) {
            case 'free':
                return $free ? [self::WEIGHTS['budget'], 'Free to use'] : [0, null];
            case 'open_source':
                return ((int) ($s['is_open_source'] ?? 0) === 1)
                    ? [self::WEIGHTS['budget'], 'Open source'] : [0, null];
            case 'freemium':
                return in_array($price, ['freemium', 'free'], true)
                    ? [self::WEIGHTS['budget'], 'Free version available'] : [(int) round(self::WEIGHTS['budget'] * 0.5), null];
            default: // paid ok / any
                return [self::WEIGHTS['budget'], $free ? 'Free option available' : null];
        }
    }

    /** @return array{0:int,1:?string} */
    private static function experience(array $s, string $level, array $tags): array
    {
        $price = (string) ($s['price_type'] ?? '');
        $has = static fn(array $words) => (bool) array_intersect($words, $tags);
        if ($level === 'beginner') {
            if ($has(['beginner', 'easy', 'simple', 'basic']) || in_array($price, ['free', 'freemium'], true)) {
                return [self::WEIGHTS['experience'], 'Beginner friendly'];
            }
            return [(int) round(self::WEIGHTS['experience'] * 0.6), null];
        }
        if ($level === 'professional') {
            if ($has(['professional', 'pro', 'advanced']) || $price === 'paid') {
                return [self::WEIGHTS['experience'], 'Professional features'];
            }
            return [(int) round(self::WEIGHTS['experience'] * 0.6), null];
        }
        return [(int) round(self::WEIGHTS['experience'] * 0.8), null];
    }

    /** @return array{0:int,1:string[]} */
    private static function features(array $s, array $tags, array $prefs): array
    {
        if (!$prefs) {
            return [(int) round(self::WEIGHTS['features'] * 0.8), []];
        }
        $hay = $tags;
        $hay[] = mb_strtolower((string) ($s['name'] ?? ''));
        $hay[] = mb_strtolower((string) ($s['short_description'] ?? ''));
        $hayStr = implode(' ', $hay);
        $labels = [
            'lightweight' => 'Lightweight', 'offline' => 'Works offline', 'open_source' => 'Open source',
            'privacy' => 'Privacy focused', 'beginner' => 'Beginner friendly', 'professional' => 'Professional',
            'no_account' => 'No account needed', 'portable' => 'Portable',
        ];
        $matched = [];
        foreach ($prefs as $p) {
            $needle = str_replace('_', ' ', $p);
            if ($p === 'open_source' && (int) ($s['is_open_source'] ?? 0) === 1) { $matched[] = $p; continue; }
            if (str_contains($hayStr, $needle) || str_contains($hayStr, $p)) { $matched[] = $p; }
        }
        $pts = (int) round(self::WEIGHTS['features'] * (count($matched) / max(1, count($prefs))));
        $reasons = [];
        foreach ($matched as $m) { $reasons[] = $labels[$m] ?? ucfirst($m); }
        return [$pts, $reasons];
    }

    private static function level(int $pct): string
    {
        if ($pct >= 90) return 'Excellent Match';
        if ($pct >= 75) return 'Good Match';
        if ($pct >= 50) return 'May Work';
        return 'Not Recommended';
    }

    private static function osLabel(string $slug): string
    {
        return ['windows' => 'Windows', 'macos' => 'macOS', 'ios' => 'iOS', 'android' => 'Android', 'linux' => 'Linux'][strtolower($slug)] ?? ucfirst($slug);
    }

    private static function gb(int $mb): string
    {
        return $mb >= 1024 ? round($mb / 1024) . ' GB' : $mb . ' MB';
    }
}
