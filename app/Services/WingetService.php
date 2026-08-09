<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Http;

/**
 * Microsoft winget-pkgs metadata source. Uses the winget REST index
 * (api.winget.run style) or the GitHub manifests as configured. Data is used
 * as an additional metadata/discovery signal — existence != safety.
 *
 * Source config JSON: {"packages": ["Mozilla.Firefox", "VideoLAN.VLC"]}
 */
final class WingetService
{
    // Community REST index over winget-pkgs manifests.
    private const API = 'https://api.winget.run/v2/packages';

    public static function sync(array $source): array
    {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $config = json_decode($source['config'] ?? '{}', true) ?: [];
        $packages = $config['packages'] ?? [];

        foreach ($packages as $pkgId) {
            $stats['processed']++;
            $dto = self::fetchPackage($pkgId, $source);
            if ($dto === null) {
                $stats['failed']++;
                continue;
            }
            $result = Ingest::process($dto);
            match ($result['action']) {
                'published', 'review' => $stats['created']++,
                'updated', 'refreshed' => $stats['updated']++,
                default => $stats['skipped']++,
            };
        }
        return $stats;
    }

    public static function fetchPackage(string $pkgId, array $source): ?array
    {
        $resp = Http::get(self::API . '/' . rawurlencode($pkgId));
        if ($resp['status'] !== 200) {
            return null;
        }
        $data = json_decode($resp['body'], true);
        $pkg = $data['Package'] ?? $data['package'] ?? null;
        if (!is_array($pkg)) {
            return null;
        }

        $latest = $pkg['Latest'] ?? $pkg['latest'] ?? [];
        $name = $latest['PackageName'] ?? $pkg['Id'] ?? $pkgId;
        $publisher = $latest['Publisher'] ?? '';
        $version = $pkg['Versions'][0] ?? ($latest['PackageVersion'] ?? '');

        $signals = ($latest['ShortDescription'] ?? '') . ' ' . ($latest['Tags'] ? implode(' ', (array) $latest['Tags']) : '');
        $categoryId = Classifier::detectCategory($name, $signals);

        return [
            'name'                  => $name,
            'developer_name'        => $publisher,
            'official_website'      => $latest['PublisherUrl'] ?? ($latest['Homepage'] ?? ''),
            'official_download_url' => $latest['Homepage'] ?? '',
            'short_description'     => str_excerpt($latest['ShortDescription'] ?? '', 300),
            'long_description'      => $latest['Description'] ?? ($latest['ShortDescription'] ?? ''),
            'version'               => is_string($version) ? $version : '',
            'license_type'          => $latest['License'] ?? null,
            'operating_system'      => 'Windows',
            'category_id'           => $categoryId,
            'source_id'             => (int) $source['id'],
            'source_type'           => 'winget',
            'source_url'            => 'https://github.com/microsoft/winget-pkgs',
            'external_ref'          => 'winget:' . strtolower($pkgId),
        ];
    }
}
