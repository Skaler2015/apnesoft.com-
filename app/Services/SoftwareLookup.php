<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Support\Http;

/**
 * Free "auto-fill from name" lookup for the admin Add-Software form.
 *
 * Given just a product name, it searches free public catalogues — winget
 * (winget.run), the Chocolatey community feed and the GitHub search API — and
 * returns the best-matching entry's REAL metadata (developer, official website,
 * download URL, description, version, licence, logo). No API key, no cost, and
 * nothing invented: every field comes from an official source, so factual data
 * (unlike an LLM guess) can be trusted. Returns null when no confident match is
 * found so the admin can fill the form manually.
 */
final class SoftwareLookup
{
    /** @return array<string,mixed>|null best match, normalised for the form */
    public static function search(string $name): ?array
    {
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            return null;
        }
        $target = self::norm($name);

        // 1) Curated popular apps first — always the vendor's OFFICIAL link,
        //    highest trust (e.g. "Telegram" -> telegram.org, not a random repo).
        $pop = CatalogImport::popularApp($name);
        if ($pop !== null) {
            return self::finish($pop, 100);
        }

        // 2) Official app catalogues (winget, Chocolatey) — official homepages.
        [$best, $score] = self::pickBest($target, array_merge(self::winget($name), self::chocolatey($name)));
        if ($best !== null && $score >= 65) {
            return self::finish($best, $score);
        }

        // 3) GitHub (open-source projects) as a fallback only.
        [$gh, $ghScore] = self::pickBest($target, self::github($name));
        if ($gh !== null && $ghScore >= 62) {
            return self::finish($gh, $ghScore);
        }

        // 4) A weaker official match, if we had one.
        if ($best !== null && $score >= 55) {
            return self::finish($best, $score);
        }
        return null;
    }

    /**
     * Deepen an existing lookup result using the software's own official page:
     * schema.org SoftwareApplication JSON-LD (accurate version / OS / file size /
     * release date / price / screenshots) plus a few conservative text fallbacks.
     * Only fills gaps — a value already found is kept unless the page is clearly
     * more authoritative (version, file size, release date).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function enrichFromPage(array $data, string $html, string $url = ''): array
    {
        if ($html === '') {
            return $data;
        }
        $ld = Publisher::parseJsonLd($html);

        // A real logo from the page (apple-touch-icon) beats a generic favicon.
        $isGenericFavicon = empty($data['logo']) || str_contains((string) $data['logo'], 's2/favicons');
        if ($url !== '' && $isGenericFavicon && ($icon = Publisher::extractIcon($html, $url)) !== null) {
            $data['logo'] = $icon;
        }

        $fill = static function (string $key, $val, bool $force = false) use (&$data): void {
            $val = is_string($val) ? trim($val) : $val;
            if ($val === null || $val === '' || $val === []) {
                return;
            }
            if ($force || empty($data[$key])) {
                $data[$key] = $val;
            }
        };

        // JSON-LD is authoritative for these hard facts.
        $fill('version', $ld['version'] ?? '', true);
        $fill('file_size', $ld['file_size'] ?? '', true);
        $fill('release_date', $ld['release_date'] ?? '', true);
        $fill('operating_system', $ld['operating_system'] ?? '');
        $fill('developer_name', $ld['developer_name'] ?? '');
        $fill('official_download_url', $ld['official_download_url'] ?? '');
        $fill('minimum_requirements', $ld['minimum_requirements'] ?? '');
        $fill('price_type', $ld['price_type'] ?? '');
        if (!empty($ld['screenshots'])) {
            $fill('screenshots', $ld['screenshots'], true);
        }

        // Text fallbacks when the page has no structured data.
        $text = (string) preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $html);
        if (empty($data['version'])) {
            foreach ([
                '~\b(?:latest\s+)?version\s*[:\-]?\s*v?(\d+(?:\.\d+){1,3})~i',
                '~\bv(?:ersion)?\.?\s*(\d+(?:\.\d+){2,3})\b~i',
                '~\b(?:release|build)\s*[:\-]?\s*v?(\d+(?:\.\d+){1,3})~i',
            ] as $pat) {
                if (preg_match($pat, $text, $m)) { $data['version'] = $m[1]; break; }
            }
        }
        if (empty($data['file_size']) && preg_match('~\b(?:file\s*size|download\s*size|installer\s*size|size)\b\s*[:\-]?\s*(\d+(?:\.\d+)?\s?(?:kb|mb|gb))~i', $text, $m)) {
            $data['file_size'] = strtoupper((string) preg_replace('~\s+~', ' ', trim($m[1])));
        }
        return $data;
    }

    /** @return array{0:?array,1:int} best candidate + its score */
    private static function pickBest(string $target, array $cands): array
    {
        $best = null;
        $bs = 0;
        foreach ($cands as $c) {
            $s = self::score($target, self::norm((string) $c['name']));
            if ($s > $bs) {
                $bs = $s;
                $best = $c;
            }
        }
        return [$best, $bs];
    }

    /** Attach a suggested category + confidence + logo, drop empty fields. */
    private static function finish(array $best, int $score): array
    {
        $best['category_id'] = Classifier::detectCategory((string) $best['name'],
            (string) ($best['long_description'] ?? '') . ' ' . (string) ($best['signals'] ?? ''));
        // Give it a logo — Google's favicon service for the official domain — when none was found.
        if (empty($best['logo'])) {
            foreach (['official_website', 'official_download_url', 'developer_website'] as $field) {
                if (!empty($best[$field]) && ($host = parse_url((string) $best[$field], PHP_URL_HOST))) {
                    $best['logo'] = 'https://www.google.com/s2/favicons?domain=' . $host . '&sz=128';
                    break;
                }
            }
        }
        $best['match'] = $score;
        unset($best['signals']);
        return array_filter($best, static fn($v) => $v !== null && $v !== '');
    }

    // -- providers -------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    private static function winget(string $name): array
    {
        $url = 'https://api.winget.run/v2/packages?take=5&query=' . rawurlencode($name);
        $resp = Http::get($url, ['Accept: application/json'], 15);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            return [];
        }
        $data = json_decode($resp['body'], true);
        $out = [];
        foreach (($data['Packages'] ?? []) as $p) {
            $latest = is_array($p['Latest'] ?? null) ? $p['Latest'] : [];
            $home = (string) ($latest['Homepage'] ?? '');
            $versions = is_array($p['Versions'] ?? null) ? $p['Versions'] : [];
            $out[] = [
                'name'                  => (string) ($latest['Name'] ?? ($p['Id'] ?? '')),
                'developer_name'        => (string) ($latest['Publisher'] ?? self::host($home)),
                'official_website'      => $home ?: null,
                'official_download_url' => $home ?: null,
                'short_description'     => str_excerpt((string) ($latest['Description'] ?? ''), 300),
                'long_description'      => (string) ($latest['Description'] ?? ''),
                'version'               => $versions ? (string) end($versions) : null,
                'license_type'          => $latest['License'] ?? null,
                'is_open_source'        => 0,
                'operating_system'      => 'Windows',
                'logo'                  => $p['Logo'] ?? null,
                'source'                => 'winget',
                'signals'               => implode(' ', (array) ($latest['Tags'] ?? [])),
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private static function chocolatey(string $name): array
    {
        $url = 'https://community.chocolatey.org/api/v2/Search()?$filter=IsLatestVersion&$top=5'
            . '&searchTerm=%27' . rawurlencode($name) . '%27&targetFramework=%27%27&includePrerelease=false';
        $resp = Http::get($url, ['Accept: application/atom+xml'], 15);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            return [];
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($resp['body']);
        if ($xml === false) {
            return [];
        }
        $out = [];
        foreach ($xml->children('http://www.w3.org/2005/Atom')->entry as $entry) {
            $meta = $entry->children('http://schemas.microsoft.com/ado/2007/08/dataservices/metadata');
            if (!isset($meta->properties)) {
                continue;
            }
            $props = $meta->properties->children('http://schemas.microsoft.com/ado/2007/08/dataservices');
            $get = static fn(string $k): string => trim((string) ($props->$k ?? ''));
            $project = $get('ProjectUrl');
            $out[] = [
                'name'                  => $get('Title') ?: $get('Id'),
                'developer_name'        => self::host($project) ?: $get('Authors'),
                'official_website'      => $project ?: null,
                'official_download_url' => $project ?: null,
                'short_description'     => str_excerpt($get('Description'), 300),
                'long_description'      => $get('Description'),
                'version'               => $get('Version') ?: null,
                'license_type'          => $get('LicenseUrl') ? 'See licence' : null,
                'is_open_source'        => 0,
                'operating_system'      => 'Windows',
                'logo'                  => $get('IconUrl') ?: null,
                'source'                => 'chocolatey',
                'signals'               => $get('Tags'),
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private static function github(string $name): array
    {
        $token = (string) Config::get('integrations.github_token', '');
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $url = 'https://api.github.com/search/repositories?per_page=5&sort=stars&order=desc&q='
            . rawurlencode($name . ' in:name');
        $resp = Http::get($url, $headers, 15);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            return [];
        }
        $data = json_decode($resp['body'], true);
        $out = [];
        foreach (($data['items'] ?? []) as $repo) {
            $html = (string) ($repo['html_url'] ?? '');
            $out[] = [
                'name'                  => (string) ($repo['name'] ?? ''),
                'developer_name'        => (string) ($repo['owner']['login'] ?? ''),
                'developer_website'     => (string) ($repo['owner']['html_url'] ?? ''),
                'official_website'      => $repo['homepage'] ?: $html,
                'official_download_url' => $html !== '' ? $html . '/releases' : null,
                'short_description'     => str_excerpt((string) ($repo['description'] ?? ''), 300),
                'long_description'      => (string) ($repo['description'] ?? ''),
                'version'               => null,
                'license_type'          => self::cleanLicense($repo['license']['spdx_id'] ?? ($repo['license']['name'] ?? null)),
                'price_type'            => 'open_source',
                'is_open_source'        => 1,
                'operating_system'      => 'Windows, macOS, Linux',
                'logo'                  => $repo['owner']['avatar_url'] ?? null,
                'source'                => 'github',
                'signals'               => implode(' ', (array) ($repo['topics'] ?? [])) . ' ' . (string) ($repo['language'] ?? ''),
            ];
        }
        return $out;
    }

    // -- helpers ---------------------------------------------------------------

    /** GitHub returns "NOASSERTION" when it can't detect a licence — drop it. */
    private static function cleanLicense(?string $lic): ?string
    {
        $lic = trim((string) $lic);
        return ($lic === '' || strcasecmp($lic, 'NOASSERTION') === 0) ? null : $lic;
    }

    private static function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($s))) ?? '';
    }

    /** 0–100 name-match score. */
    private static function score(string $target, string $candidate): int
    {
        if ($candidate === '' || $target === '') {
            return 0;
        }
        if ($candidate === $target) {
            return 100;
        }
        if (str_starts_with($candidate, $target) || str_starts_with($target, $candidate)) {
            return 80;
        }
        if (str_contains($candidate, $target) || str_contains($target, $candidate)) {
            return 65;
        }
        similar_text($target, $candidate, $pct);
        return (int) round($pct);
    }

    private static function host(string $url): string
    {
        $h = parse_url($url, PHP_URL_HOST);
        return $h ? (preg_replace('/^www\./', '', (string) $h) ?? '') : '';
    }
}
