<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Heuristic category + OS detection from text signals (topics, description).
 * Returns best-effort category_id; leaves null when uncertain (-> review).
 */
final class Classifier
{
    /** keyword => category slug */
    private const KEYWORDS = [
        'pdf' => 'pdf-tools', 'video edit' => 'video-editing', 'video' => 'video-editing',
        'screen record' => 'screen-recording', 'record' => 'screen-recording',
        'photo' => 'photo-editing', 'image edit' => 'photo-editing', 'antivirus' => 'security',
        'security' => 'security', 'compress' => 'file-tools', 'archive' => 'file-tools',
        'browser' => 'browsers', 'editor' => 'developer-tools', 'ide' => 'developer-tools',
        'code' => 'developer-tools', 'terminal' => 'developer-tools', 'git' => 'developer-tools',
        'office' => 'office', 'document' => 'office', 'backup' => 'backup',
        'remote desktop' => 'remote-tools', 'remote' => 'remote-tools', 'vpn' => 'security',
        'music' => 'media-players', 'player' => 'media-players', 'audio' => 'media-players',
        'download' => 'utilities', 'cleaner' => 'utilities', 'utility' => 'utilities',
        'chat' => 'communication', 'messaging' => 'communication', 'note' => 'productivity',
    ];

    public static function detectCategory(string ...$signals): ?int
    {
        $text = strtolower(implode(' ', array_filter($signals)));
        foreach (self::KEYWORDS as $kw => $slug) {
            if (str_contains($text, $kw)) {
                $id = Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $slug]);
                if ($id) {
                    return (int) $id;
                }
            }
        }
        return null;
    }

    /** @return int[] os ids */
    public static function detectOsIds(string ...$signals): array
    {
        $text = strtolower(implode(' ', array_filter($signals)));
        $map = [
            'windows' => ['windows', 'win32', 'win64', '.exe', '.msi'],
            'macos'   => ['macos', 'mac os', 'osx', 'darwin', '.dmg', '.pkg'],
            'linux'   => ['linux', 'ubuntu', 'debian', '.deb', '.rpm', '.appimage', 'gnu'],
            'android' => ['android', '.apk'],
        ];
        $ids = [];
        foreach ($map as $slug => $needles) {
            foreach ($needles as $n) {
                if (str_contains($text, $n)) {
                    $id = Database::scalar('SELECT id FROM operating_systems WHERE slug = :s', ['s' => $slug]);
                    if ($id) {
                        $ids[] = (int) $id;
                    }
                    break;
                }
            }
        }
        return array_unique($ids);
    }

    public static function osLabel(array $osIds): string
    {
        if (empty($osIds)) {
            return '';
        }
        $in = implode(',', array_map('intval', $osIds));
        $names = Database::all("SELECT name FROM operating_systems WHERE id IN ($in) ORDER BY sort_order");
        return implode(', ', array_column($names, 'name'));
    }
}
