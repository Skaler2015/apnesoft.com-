<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Semantic-version-aware comparison used by the Version Detection Engine.
 * Normalizes tags like "v1.2.3", "1.2.3-beta", "2024.05" into comparable form.
 */
final class Version
{
    /** Strip a leading v/release prefix and surrounding noise. */
    public static function normalize(string $raw): string
    {
        $raw = trim($raw);
        // Extract the first version-looking token, e.g. from "Release v1.2.3 (stable)".
        if (preg_match('/(\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.\-]+)?)/', $raw, $m)) {
            return ltrim($m[1], 'vV');
        }
        return ltrim($raw, 'vV');
    }

    /**
     * Compare two versions. Returns -1, 0, or 1 (a<b, a==b, a>b).
     * Handles numeric dotted parts and simple pre-release ordering.
     */
    public static function compare(string $a, string $b): int
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === $b) {
            return 0;
        }

        // Split core version and pre-release.
        [$coreA, $preA] = self::split($a);
        [$coreB, $preB] = self::split($b);

        $partsA = array_map('intval', explode('.', $coreA));
        $partsB = array_map('intval', explode('.', $coreB));
        $len = max(count($partsA), count($partsB));

        for ($i = 0; $i < $len; $i++) {
            $x = $partsA[$i] ?? 0;
            $y = $partsB[$i] ?? 0;
            if ($x !== $y) {
                return $x <=> $y;
            }
        }

        // Equal cores: a version without pre-release outranks one with it.
        if ($preA === '' && $preB !== '') return 1;
        if ($preA !== '' && $preB === '') return -1;
        return strcmp($preA, $preB) <=> 0;
    }

    /** True when $candidate is strictly newer than $current. */
    public static function isNewer(string $candidate, string $current): bool
    {
        if (trim($current) === '') {
            return trim($candidate) !== '';
        }
        return self::compare($candidate, $current) === 1;
    }

    /** @return array{0:string,1:string} core, prerelease */
    private static function split(string $v): array
    {
        $v = explode('+', $v)[0]; // drop build metadata
        if (str_contains($v, '-')) {
            [$core, $pre] = explode('-', $v, 2);
            return [$core, $pre];
        }
        return [$v, ''];
    }
}
