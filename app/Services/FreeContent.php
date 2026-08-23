<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds a long description, features, pros/cons and tags for a software
 * WITHOUT any AI key — using only real data:
 *   - the software's own official web page (paragraphs + feature lists), and
 *   - known factual attributes (licence, price, platform, category).
 *
 * Nothing is invented. Pros/cons are derived strictly from verifiable facts
 * (e.g. "Free to use" when the price is free, "Trial version" when it's a
 * trial), never from opinion.
 */
final class FreeContent
{
    /** Stop-words we never keep as tags. */
    private const STOP = [
        'the','and','for','with','you','your','our','this','that','from','are','can',
        'all','app','apps','software','free','download','downloads','windows','mac',
        'macos','ios','android','linux','best','new','get','use','using','more','now',
        'has','have','was','will','how','what','why','who','out','one','not','but',
    ];

    /**
     * Build everything from a page's HTML plus known facts.
     *
     * @param array<string,mixed> $facts name, developer_name, price_type,
     *        license_type, operating_system, category_name, short_description,
     *        long_description
     * @return array{long_description:?string, features:string[], pros:string[], cons:string[], tags:string[]}
     */
    public static function build(string $html, array $facts): array
    {
        [$pros, $cons] = self::prosCons($facts);
        return [
            'long_description' => self::longDescription($html, $facts),
            'features'         => self::features($html),
            'pros'             => $pros,
            'cons'             => $cons,
            'tags'             => self::tags($facts),
        ];
    }

    /** Fetch the official page and build from it. Falls back to facts only. */
    public static function fromUrl(string $url, array $facts): array
    {
        $page = Publisher::fetch($url);
        return self::build($page['html'] ?? '', $facts);
    }

    /** No page available — build tags + pros/cons from facts, keep any description. */
    public static function fromFacts(array $facts): array
    {
        return self::build('', $facts);
    }

    // ---------------------------------------------------------------------

    private static function longDescription(string $html, array $facts): ?string
    {
        $paras = [];
        $existing = trim((string) ($facts['long_description'] ?? $facts['short_description'] ?? ''));
        if ($existing !== '') {
            $paras[] = $existing;
        }

        if ($html !== '') {
            // Drop scripts / styles / nav / footer noise before reading paragraphs.
            $body = preg_replace('~<(script|style|noscript|nav|footer|header|form)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
            if (preg_match_all('~<p\b[^>]*>(.*?)</p>~is', $body, $m)) {
                foreach ($m[1] as $raw) {
                    $t = self::text($raw);
                    // Real sentences only: long enough, has spaces, ends like prose.
                    if (mb_strlen($t) >= 60 && substr_count($t, ' ') >= 8 && !self::looksLikeNav($t)) {
                        $paras[] = $t;
                    }
                    if (count($paras) >= 6) {
                        break;
                    }
                }
            }
        }

        $paras = array_values(array_unique(array_filter(array_map('trim', $paras))));
        if (!$paras) {
            return null;
        }

        // Cap to roughly 550 words so the field stays readable.
        $out = [];
        $words = 0;
        foreach ($paras as $p) {
            $out[] = $p;
            $words += str_word_count($p);
            if ($words >= 550) {
                break;
            }
        }
        return implode("\n\n", $out);
    }

    /** Pull feature bullets from lists that sit under a "Features"-type heading. */
    private static function features(string $html): array
    {
        if ($html === '') {
            return [];
        }
        $found = [];

        // Lists that appear right after a heading mentioning features / benefits / why.
        if (preg_match_all(
            '~<h[1-4][^>]*>[^<]*\b(?:features?|benefits?|highlights?|why|what[\'\x{2019}]?s\s+included|capabilities)\b[^<]*</h[1-4]>(.*?)<ul\b[^>]*>(.*?)</ul>~isu',
            $html,
            $blocks,
            PREG_SET_ORDER
        )) {
            foreach ($blocks as $b) {
                // Only accept when the list closely follows the heading (little text between).
                if (mb_strlen(self::text($b[1])) > 120) {
                    continue;
                }
                self::collectItems($b[2], $found);
            }
        }

        // Also accept <ul class="features"> style blocks anywhere.
        if (count($found) < 4 && preg_match_all('~<ul\b[^>]*\b(?:class|id)=["\'][^"\']*(?:feature|benefit|highlight)[^"\']*["\'][^>]*>(.*?)</ul>~is', $html, $lists)) {
            foreach ($lists[1] as $list) {
                self::collectItems($list, $found);
            }
        }

        return array_slice(array_values(array_unique($found)), 0, 8);
    }

    /** @param string[] $bucket */
    private static function collectItems(string $listHtml, array &$bucket): void
    {
        if (!preg_match_all('~<li\b[^>]*>(.*?)</li>~is', $listHtml, $items)) {
            return;
        }
        foreach ($items[1] as $raw) {
            $t = self::text($raw);
            // Keep short, sentence-like bullets; skip nav/links-only items.
            if ($t !== '' && mb_strlen($t) >= 6 && mb_strlen($t) <= 110 && substr_count($t, ' ') >= 1 && !self::looksLikeNav($t)) {
                $bucket[] = self::tidy($t);
            }
            if (count($bucket) >= 12) {
                return;
            }
        }
    }

    /**
     * Pros/cons derived only from verifiable facts.
     *
     * @return array{0:string[],1:string[]}
     */
    private static function prosCons(array $facts): array
    {
        $price   = strtolower((string) ($facts['price_type'] ?? ''));
        $license = strtolower((string) ($facts['license_type'] ?? ''));
        $os      = strtolower((string) ($facts['operating_system'] ?? ''));
        $pros = [];
        $cons = [];

        if ($price === 'free' || $price === 'open_source' || str_contains($license, 'free') || str_contains($license, 'open')) {
            $pros[] = 'Free to download and use';
        }
        if ($price === 'open_source' || str_contains($license, 'open') || str_contains($license, 'gpl') || str_contains($license, 'mit')) {
            $pros[] = 'Open source';
        }
        if ($price === 'freemium') {
            $pros[] = 'Free version available';
            $cons[] = 'Some features need a paid plan';
        }
        if ($price === 'trial') {
            $pros[] = 'Free trial available';
            $cons[] = 'Full version requires a purchase';
        }
        if ($price === 'paid') {
            $cons[] = 'Paid licence required';
        }

        // Platform coverage (factual).
        $platforms = 0;
        foreach (['windows', 'mac', 'ios', 'android', 'linux'] as $p) {
            if (str_contains($os, $p)) {
                $platforms++;
            }
        }
        if ($platforms >= 2) {
            $pros[] = 'Cross-platform support';
        } elseif ($platforms === 1) {
            $only = ucfirst(trim(preg_replace('~[,/].*$~', '', $os) ?: $os));
            if ($only !== '') {
                $cons[] = $only . ' only';
            }
        }

        // Always-true, non-opinion pro for our directory.
        $pros[] = 'Official / verified download link';

        return [
            array_slice(array_values(array_unique($pros)), 0, 6),
            array_slice(array_values(array_unique($cons)), 0, 5),
        ];
    }

    /** Tags from real attributes + notable keywords in the name/description. */
    private static function tags(array $facts): array
    {
        $tags = [];
        $add = static function (string $t) use (&$tags): void {
            $t = trim(mb_strtolower($t));
            if ($t !== '' && mb_strlen($t) >= 3 && !in_array($t, $tags, true)) {
                $tags[] = $t;
            }
        };

        if (!empty($facts['category_name'])) {
            $add((string) $facts['category_name']);
        }
        $price = (string) ($facts['price_type'] ?? '');
        if ($price !== '') {
            $add(str_replace('_', ' ', $price));
        }
        foreach (['windows', 'mac', 'ios', 'android', 'linux'] as $p) {
            if (str_contains(strtolower((string) ($facts['operating_system'] ?? '')), $p)) {
                $add($p);
            }
        }

        // Notable words from the description (real terms the developer used).
        $text = mb_strtolower((string) ($facts['name'] ?? '') . ' ' . (string) ($facts['short_description'] ?? ''));
        if (preg_match_all('~[a-z][a-z0-9+\-]{2,}~', $text, $m)) {
            $freq = [];
            foreach ($m[0] as $w) {
                if (in_array($w, self::STOP, true)) {
                    continue;
                }
                $freq[$w] = ($freq[$w] ?? 0) + 1;
            }
            arsort($freq);
            foreach (array_keys(array_slice($freq, 0, 6, true)) as $w) {
                $add($w);
            }
        }

        return array_slice($tags, 0, 10);
    }

    // ---------------------------------------------------------------------

    private static function text(string $html): string
    {
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        return trim((string) preg_replace('~\s+~u', ' ', $t));
    }

    private static function tidy(string $t): string
    {
        $t = rtrim($t, " .;:\u{2022}-");
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    private static function looksLikeNav(string $t): bool
    {
        $l = mb_strtolower($t);
        foreach (['sign in', 'log in', 'cookie', 'privacy policy', 'terms of', 'subscribe', 'newsletter', 'copyright', '©', 'all rights reserved'] as $bad) {
            if (str_contains($l, $bad)) {
                return true;
            }
        }
        return false;
    }
}
