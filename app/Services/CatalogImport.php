<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Support\Http;

/**
 * Imports real software from official multi-platform app catalogs with public
 * APIs — Homebrew casks (macOS), Flathub (Linux) and F-Droid (Android).
 * Only genuine, authorized entries with official links are published; large
 * catalog files are cached to disk and imported a batch at a time via a cursor.
 */
final class CatalogImport
{
    /** Catalogs rotated through automatically by the cron. */
    private const ROTATING = ['chocolatey', 'homebrew', 'flathub', 'fdroid'];

    /** All sources triggerable manually from the admin. */
    private const MANUAL = ['popular', 'chocolatey', 'fdroid', 'homebrew', 'flathub'];

    public static function sources(): array
    {
        return self::MANUAL;
    }

    /** Run one batch for the named source. */
    public static function run(string $source, int $maxNew = 150): array
    {
        return match ($source) {
            'popular'    => self::popular(),
            'chocolatey' => self::chocolatey($maxNew),
            'homebrew'   => self::homebrew($maxNew),
            'flathub'    => self::flathub($maxNew),
            'fdroid'     => self::fdroid($maxNew),
            default      => self::zero('unknown source'),
        };
    }

    /** Rotate through the catalogs (one per call) — used by the hourly cron. */
    public static function runRotating(int $maxNew = 150): array
    {
        $turn = self::intSetting('catalog_turn', 0) % count(self::ROTATING);
        self::setSetting('catalog_turn', (string) ($turn + 1));
        $source = self::ROTATING[$turn];
        $r = self::run($source, $maxNew);
        $r['source'] = $source;
        return $r;
    }

    // -- Windows: Chocolatey community feed (sorted by popularity) -------------
    private static function chocolatey(int $maxNew): array
    {
        $off = self::intSetting('choco_off', 0);
        $created = $skipped = $scanned = 0;
        $pages = 0;

        while ($created < $maxNew && $pages < 3) {
            $url = 'https://community.chocolatey.org/api/v2/Search()?'
                . '$filter=IsLatestVersion&$orderby=DownloadCount%20desc&$top=100&$skip=' . $off
                . '&searchTerm=%27%27&targetFramework=%27%27&includePrerelease=false';
            $resp = Http::get($url, ['Accept: application/atom+xml'], 30);
            if ($resp['status'] !== 200 || $resp['body'] === '') {
                break;
            }
            $entries = self::parseChocoXml($resp['body']);
            if (empty($entries)) {
                break;
            }
            foreach ($entries as $p) {
                $scanned++;
                $id = $p['Id'] ?? '';
                if ($id === '') {
                    $skipped++;
                    continue;
                }
                $official = $p['ProjectUrl'] ?: ('https://community.chocolatey.org/packages/' . $id);
                $dto = [
                    'external_ref'          => 'choco:' . strtolower($id),
                    'name'                  => $p['Title'] ?: $id,
                    'developer_name'        => self::host($p['ProjectUrl'] ?? '') ?: ($p['Authors'] ?? ''),
                    'official_website'      => $official,
                    'official_download_url' => $official,
                    'short_description'     => str_excerpt($p['Description'] ?? '', 300),
                    'long_description'      => $p['Description'] ?? '',
                    'version'               => $p['Version'] ?? null,
                    'price_type'            => null,
                    'is_open_source'        => 0,
                    'os_slug'               => 'windows',
                    'os_label'              => 'Windows',
                    'logo'                  => $p['IconUrl'] ?? null,
                    'signals'               => ($p['Description'] ?? '') . ' ' . ($p['Tags'] ?? ''),
                ];
                try {
                    self::store($dto) ? $created++ : $skipped++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
                if ($created >= $maxNew) {
                    break;
                }
            }
            $off += count($entries);
            $pages++;
        }
        self::setSetting('choco_off', (string) $off);
        return ['created' => $created, 'skipped' => $skipped, 'scanned' => $scanned, 'message' => 'imported ' . $created];
    }

    /** @return array<int, array<string,string>> flattened package properties */
    private static function parseChocoXml(string $body): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            return [];
        }
        $atom = $xml->children('http://www.w3.org/2005/Atom');
        $entries = [];
        foreach ($atom->entry as $entry) {
            $meta = $entry->children('http://schemas.microsoft.com/ado/2007/08/dataservices/metadata');
            if (!isset($meta->properties)) {
                continue;
            }
            $props = $meta->properties->children('http://schemas.microsoft.com/ado/2007/08/dataservices');
            $row = [];
            foreach ($props as $name => $value) {
                $row[$name] = trim((string) $value);
            }
            $entries[] = $row;
        }
        return $entries;
    }

    // -- Curated popular apps (household names, official links) ----------------
    private static function popular(): array
    {
        $created = $skipped = 0;
        foreach (self::POPULAR as $a) {
            $dto = [
                'external_ref'          => 'popular:' . \slugify($a[0]),
                'name'                  => $a[0],
                'developer_name'        => $a[1],
                'official_website'      => $a[2],
                'official_download_url' => $a[3] ?: $a[2],
                'short_description'     => $a[4],
                'long_description'      => $a[4],
                'version'               => null,
                'license_type'          => null,
                'price_type'            => $a[6] ?? null,
                'is_open_source'        => ($a[6] ?? '') === 'open_source' ? 1 : 0,
                'os_slug'               => $a[7] ?? 'windows',
                'os_label'              => $a[8] ?? 'Windows',
                'signals'               => $a[0] . ' ' . $a[5] . ' ' . $a[4],
                'category_slug'         => $a[5],
            ];
            try {
                self::store($dto) ? $created++ : $skipped++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }
        return ['created' => $created, 'skipped' => $skipped, 'scanned' => count(self::POPULAR), 'message' => 'popular apps imported'];
    }

    /** [name, developer, website, download_url, description, category_slug, price_type, os_slug, os_label] */
    private const POPULAR = [
        ['Google Chrome', 'Google', 'https://www.google.com/chrome/', 'https://www.google.com/chrome/', 'Fast, secure web browser from Google.', 'browsers', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Microsoft Edge', 'Microsoft', 'https://www.microsoft.com/edge', 'https://www.microsoft.com/edge', 'Chromium-based browser built into Windows.', 'browsers', 'free', 'windows', 'Windows, macOS'],
        ['Brave Browser', 'Brave Software', 'https://brave.com/', 'https://brave.com/download/', 'Privacy-focused browser that blocks ads and trackers.', 'browsers', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Opera', 'Opera', 'https://www.opera.com/', 'https://www.opera.com/download', 'Feature-rich browser with built-in VPN and ad blocker.', 'browsers', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Zoom', 'Zoom Video Communications', 'https://zoom.us/', 'https://zoom.us/download', 'Video conferencing and online meetings.', 'communication', 'freemium', 'windows', 'Windows, macOS'],
        ['Skype', 'Microsoft', 'https://www.skype.com/', 'https://www.skype.com/get-skype/', 'Voice, video calls and messaging.', 'communication', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Microsoft Teams', 'Microsoft', 'https://www.microsoft.com/microsoft-teams', 'https://www.microsoft.com/microsoft-teams/download-app', 'Team chat, meetings and collaboration.', 'communication', 'freemium', 'windows', 'Windows, macOS'],
        ['Slack', 'Slack Technologies', 'https://slack.com/', 'https://slack.com/downloads', 'Team messaging and collaboration hub.', 'communication', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['Discord', 'Discord Inc.', 'https://discord.com/', 'https://discord.com/download', 'Voice, video and text chat for communities.', 'communication', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Telegram Desktop', 'Telegram', 'https://telegram.org/', 'https://desktop.telegram.org/', 'Fast, secure cloud-based messaging.', 'communication', 'free', 'windows', 'Windows, macOS, Linux'],
        ['WhatsApp Desktop', 'Meta', 'https://www.whatsapp.com/', 'https://www.whatsapp.com/download', 'Desktop client for WhatsApp messaging.', 'communication', 'free', 'windows', 'Windows, macOS'],
        ['Signal', 'Signal Foundation', 'https://signal.org/', 'https://signal.org/download/', 'Private messenger with end-to-end encryption.', 'communication', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Spotify', 'Spotify', 'https://www.spotify.com/', 'https://www.spotify.com/download', 'Music streaming for millions of songs.', 'media-players', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['Adobe Acrobat Reader', 'Adobe', 'https://get.adobe.com/reader/', 'https://get.adobe.com/reader/', 'View, print and annotate PDF documents.', 'pdf-tools', 'free', 'windows', 'Windows, macOS'],
        ['WinRAR', 'RARLAB', 'https://www.win-rar.com/', 'https://www.win-rar.com/download.html', 'Powerful archiver for RAR and ZIP files.', 'file-tools', 'trial', 'windows', 'Windows'],
        ['Zoom Player', 'Inmatrix', 'https://www.inmatrix.com/', 'https://www.inmatrix.com/zplayer/', 'Advanced media player for Windows.', 'media-players', 'freemium', 'windows', 'Windows'],
        ['VLC media player', 'VideoLAN', 'https://www.videolan.org/vlc/', 'https://www.videolan.org/vlc/', 'Free, open-source cross-platform media player.', 'media-players', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Notepad++', 'Notepad++ Team', 'https://notepad-plus-plus.org/', 'https://notepad-plus-plus.org/downloads/', 'Free source code and text editor.', 'developer-tools', 'open_source', 'windows', 'Windows'],
        ['Visual Studio Code', 'Microsoft', 'https://code.visualstudio.com/', 'https://code.visualstudio.com/download', 'Lightweight, powerful source code editor.', 'developer-tools', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Sublime Text', 'Sublime HQ', 'https://www.sublimetext.com/', 'https://www.sublimetext.com/download', 'Sophisticated text editor for code and prose.', 'developer-tools', 'trial', 'windows', 'Windows, macOS, Linux'],
        ['Git', 'Software Freedom Conservancy', 'https://git-scm.com/', 'https://git-scm.com/downloads', 'Distributed version control system.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Python', 'Python Software Foundation', 'https://www.python.org/', 'https://www.python.org/downloads/', 'Popular programming language and runtime.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Node.js', 'OpenJS Foundation', 'https://nodejs.org/', 'https://nodejs.org/en/download', 'JavaScript runtime built on Chrome V8.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Steam', 'Valve', 'https://store.steampowered.com/', 'https://store.steampowered.com/about/', 'Digital game store and launcher.', 'utilities', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Epic Games Launcher', 'Epic Games', 'https://www.epicgames.com/', 'https://www.epicgames.com/store/download', 'Store and launcher for Epic games.', 'utilities', 'free', 'windows', 'Windows, macOS'],
        ['OBS Studio', 'OBS Project', 'https://obsproject.com/', 'https://obsproject.com/download', 'Free software for recording and live streaming.', 'screen-recording', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['GIMP', 'The GIMP Team', 'https://www.gimp.org/', 'https://www.gimp.org/downloads/', 'Free and open-source image editor.', 'photo-editing', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Blender', 'Blender Foundation', 'https://www.blender.org/', 'https://www.blender.org/download/', 'Free 3D creation suite.', 'photo-editing', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Audacity', 'Audacity Team', 'https://www.audacityteam.org/', 'https://www.audacityteam.org/download/', 'Free, open-source audio editor and recorder.', 'media-players', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['LibreOffice', 'The Document Foundation', 'https://www.libreoffice.org/', 'https://www.libreoffice.org/download/download/', 'Free, powerful office suite.', 'office', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['7-Zip', 'Igor Pavlov', 'https://www.7-zip.org/', 'https://www.7-zip.org/download.html', 'Free file archiver with high compression.', 'file-tools', 'open_source', 'windows', 'Windows'],
        ['Mozilla Thunderbird', 'MZLA Technologies', 'https://www.thunderbird.net/', 'https://www.thunderbird.net/download/', 'Free email client from Mozilla.', 'communication', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['AnyDesk', 'AnyDesk Software', 'https://anydesk.com/', 'https://anydesk.com/download', 'Fast remote desktop access.', 'remote-tools', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['TeamViewer', 'TeamViewer', 'https://www.teamviewer.com/', 'https://www.teamviewer.com/download/', 'Remote access and support software.', 'remote-tools', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['CCleaner', 'Piriform', 'https://www.ccleaner.com/', 'https://www.ccleaner.com/ccleaner/download', 'System cleaning and optimization utility.', 'utilities', 'freemium', 'windows', 'Windows, macOS'],
        ['Malwarebytes', 'Malwarebytes', 'https://www.malwarebytes.com/', 'https://www.malwarebytes.com/mwb-download', 'Anti-malware and threat protection.', 'security', 'freemium', 'windows', 'Windows, macOS'],
        ['Avast Free Antivirus', 'Avast', 'https://www.avast.com/', 'https://www.avast.com/free-antivirus-download', 'Free antivirus protection.', 'security', 'freemium', 'windows', 'Windows, macOS'],
        ['NordVPN', 'Nord Security', 'https://nordvpn.com/', 'https://nordvpn.com/download/', 'Secure and fast VPN service.', 'security', 'paid', 'windows', 'Windows, macOS, Linux'],
        ['qBittorrent', 'qBittorrent', 'https://www.qbittorrent.org/', 'https://www.qbittorrent.org/download', 'Free, open-source BitTorrent client.', 'utilities', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['HandBrake', 'HandBrake Team', 'https://handbrake.fr/', 'https://handbrake.fr/downloads.php', 'Open-source video transcoder.', 'video-editing', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Shotcut', 'Meltytech', 'https://www.shotcut.org/', 'https://www.shotcut.org/download/', 'Free, open-source video editor.', 'video-editing', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Krita', 'Krita Foundation', 'https://krita.org/', 'https://krita.org/en/download/', 'Free digital painting software.', 'photo-editing', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Inkscape', 'Inkscape Project', 'https://inkscape.org/', 'https://inkscape.org/release/', 'Free, open-source vector graphics editor.', 'photo-editing', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Foxit PDF Reader', 'Foxit', 'https://www.foxit.com/', 'https://www.foxit.com/pdf-reader/', 'Fast, lightweight PDF reader.', 'pdf-tools', 'freemium', 'windows', 'Windows, macOS'],
        ['Google Drive', 'Google', 'https://www.google.com/drive/', 'https://www.google.com/drive/download/', 'Cloud storage and file sync.', 'backup', 'freemium', 'windows', 'Windows, macOS'],
        ['Dropbox', 'Dropbox', 'https://www.dropbox.com/', 'https://www.dropbox.com/install', 'Cloud storage and file synchronization.', 'backup', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['PotPlayer', 'Kakao', 'https://potplayer.daum.net/', 'https://potplayer.daum.net/', 'Feature-rich multimedia player.', 'media-players', 'free', 'windows', 'Windows'],
        ['IrfanView', 'Irfan Skiljan', 'https://www.irfanview.com/', 'https://www.irfanview.com/main_download_engl.htm', 'Fast, compact image viewer.', 'photo-editing', 'free', 'windows', 'Windows'],
        ['Paint.NET', 'dotPDN', 'https://www.getpaint.net/', 'https://www.getpaint.net/download.html', 'Free image and photo editing for Windows.', 'photo-editing', 'free', 'windows', 'Windows'],
        ['Rufus', 'Pete Batard', 'https://rufus.ie/', 'https://rufus.ie/', 'Create bootable USB drives easily.', 'utilities', 'open_source', 'windows', 'Windows'],
        ['Wise Care 365', 'WiseCleaner', 'https://www.wisecleaner.com/', 'https://www.wisecleaner.com/wise-care-365.html', 'PC cleaning and optimization suite.', 'utilities', 'freemium', 'windows', 'Windows'],
        // -- Expanded popular set (official links only) ------------------------
        ['Mozilla Firefox', 'Mozilla', 'https://www.mozilla.org/firefox/', 'https://www.mozilla.org/firefox/new/', 'Free, open-source web browser.', 'browsers', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Vivaldi', 'Vivaldi Technologies', 'https://vivaldi.com/', 'https://vivaldi.com/download/', 'Highly customizable Chromium browser.', 'browsers', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Tor Browser', 'The Tor Project', 'https://www.torproject.org/', 'https://www.torproject.org/download/', 'Browse anonymously over the Tor network.', 'browsers', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Kodi', 'XBMC Foundation', 'https://kodi.tv/', 'https://kodi.tv/download/', 'Open-source home theatre media center.', 'media-players', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Plex', 'Plex Inc.', 'https://www.plex.tv/', 'https://www.plex.tv/media-server-downloads/', 'Organize and stream your media library.', 'media-players', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['foobar2000', 'Peter Pawlowski', 'https://www.foobar2000.org/', 'https://www.foobar2000.org/download', 'Lightweight, advanced audio player.', 'media-players', 'free', 'windows', 'Windows'],
        ['AIMP', 'AIMP DevTeam', 'https://www.aimp.ru/', 'https://www.aimp.ru/?do=download', 'Feature-rich audio player.', 'media-players', 'free', 'windows', 'Windows'],
        ['MusicBee', 'Steven Mayall', 'https://getmusicbee.com/', 'https://getmusicbee.com/downloads/', 'Music manager and player for large libraries.', 'media-players', 'free', 'windows', 'Windows'],
        ['K-Lite Codec Pack', 'Codec Guide', 'https://www.codecguide.com/', 'https://www.codecguide.com/download_kl.htm', 'Collection of audio and video codecs.', 'media-players', 'free', 'windows', 'Windows'],
        ['MediaMonkey', 'Ventis Media', 'https://www.mediamonkey.com/', 'https://www.mediamonkey.com/download/', 'Media organizer and player.', 'media-players', 'freemium', 'windows', 'Windows'],
        ['Everything', 'voidtools', 'https://www.voidtools.com/', 'https://www.voidtools.com/downloads/', 'Instant file and folder search for Windows.', 'utilities', 'free', 'windows', 'Windows'],
        ['CPU-Z', 'CPUID', 'https://www.cpuid.com/softwares/cpu-z.html', 'https://www.cpuid.com/softwares/cpu-z.html', 'Detailed CPU, mainboard and memory info.', 'utilities', 'free', 'windows', 'Windows'],
        ['GPU-Z', 'TechPowerUp', 'https://www.techpowerup.com/gpuz/', 'https://www.techpowerup.com/gpuz/', 'Graphics card information and monitoring.', 'utilities', 'free', 'windows', 'Windows'],
        ['HWMonitor', 'CPUID', 'https://www.cpuid.com/softwares/hwmonitor.html', 'https://www.cpuid.com/softwares/hwmonitor.html', 'Hardware temperature and voltage monitor.', 'utilities', 'free', 'windows', 'Windows'],
        ['Core Temp', 'Arthur Liberman', 'https://www.alcpu.com/CoreTemp/', 'https://www.alcpu.com/CoreTemp/', 'Monitor CPU temperature per core.', 'utilities', 'free', 'windows', 'Windows'],
        ['Sysinternals Suite', 'Microsoft', 'https://learn.microsoft.com/sysinternals/', 'https://learn.microsoft.com/sysinternals/downloads/sysinternals-suite', 'Advanced Windows troubleshooting utilities.', 'utilities', 'free', 'windows', 'Windows'],
        ['ShareX', 'ShareX Team', 'https://getsharex.com/', 'https://getsharex.com/downloads', 'Screen capture, recording and sharing.', 'screen-recording', 'open_source', 'windows', 'Windows'],
        ['Greenshot', 'Greenshot', 'https://getgreenshot.org/', 'https://getgreenshot.org/downloads/', 'Lightweight screenshot tool.', 'screen-recording', 'open_source', 'windows', 'Windows'],
        ['Flameshot', 'Flameshot', 'https://flameshot.org/', 'https://flameshot.org/#download', 'Powerful, open-source screenshot tool.', 'screen-recording', 'open_source', 'windows', 'Windows, Linux'],
        ['XnView MP', 'XnSoft', 'https://www.xnview.com/', 'https://www.xnview.com/en/xnviewmp/', 'Image viewer, converter and organizer.', 'photo-editing', 'free', 'windows', 'Windows, macOS, Linux'],
        ['FastStone Image Viewer', 'FastStone Soft', 'https://www.faststone.org/', 'https://www.faststone.org/FSViewerDownload.htm', 'Fast image viewer, browser and converter.', 'photo-editing', 'free', 'windows', 'Windows'],
        ['DaVinci Resolve', 'Blackmagic Design', 'https://www.blackmagicdesign.com/products/davinciresolve', 'https://www.blackmagicdesign.com/products/davinciresolve', 'Professional video editing and color grading.', 'video-editing', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['OpenShot', 'OpenShot Studios', 'https://www.openshot.org/', 'https://www.openshot.org/download/', 'Free, open-source video editor.', 'video-editing', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Kdenlive', 'KDE', 'https://kdenlive.org/', 'https://kdenlive.org/en/download/', 'Free and open-source video editor.', 'video-editing', 'open_source', 'windows', 'Windows, Linux'],
        ['Format Factory', 'Free Time', 'https://www.pcfreetime.com/', 'https://www.pcfreetime.com/formatfactory/index.html', 'Multifunctional media converter.', 'video-editing', 'free', 'windows', 'Windows'],
        ['Bitwarden', 'Bitwarden', 'https://bitwarden.com/', 'https://bitwarden.com/download/', 'Open-source password manager.', 'security', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['KeePass', 'Dominik Reichl', 'https://keepass.info/', 'https://keepass.info/download.html', 'Free, open-source password manager.', 'security', 'open_source', 'windows', 'Windows'],
        ['Bitdefender', 'Bitdefender', 'https://www.bitdefender.com/', 'https://www.bitdefender.com/solutions/free.html', 'Antivirus and internet security.', 'security', 'trial', 'windows', 'Windows, macOS'],
        ['Avira Free Security', 'Avira', 'https://www.avira.com/', 'https://www.avira.com/en/free-security', 'Free antivirus and system optimizer.', 'security', 'freemium', 'windows', 'Windows, macOS'],
        ['ESET', 'ESET', 'https://www.eset.com/', 'https://www.eset.com/int/home/free-trial/', 'Antivirus and endpoint security.', 'security', 'trial', 'windows', 'Windows, macOS'],
        ['Proton VPN', 'Proton AG', 'https://protonvpn.com/', 'https://protonvpn.com/download', 'Secure VPN with a free tier.', 'security', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['IntelliJ IDEA', 'JetBrains', 'https://www.jetbrains.com/idea/', 'https://www.jetbrains.com/idea/download/', 'Powerful Java and Kotlin IDE.', 'developer-tools', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['PyCharm', 'JetBrains', 'https://www.jetbrains.com/pycharm/', 'https://www.jetbrains.com/pycharm/download/', 'Python IDE for professional developers.', 'developer-tools', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['Android Studio', 'Google', 'https://developer.android.com/studio', 'https://developer.android.com/studio', 'Official IDE for Android development.', 'developer-tools', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Docker Desktop', 'Docker', 'https://www.docker.com/products/docker-desktop/', 'https://www.docker.com/products/docker-desktop/', 'Run containers on your desktop.', 'developer-tools', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['Postman', 'Postman', 'https://www.postman.com/', 'https://www.postman.com/downloads/', 'API development and testing platform.', 'developer-tools', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['FileZilla', 'Tim Kosse', 'https://filezilla-project.org/', 'https://filezilla-project.org/download.php', 'Free, open-source FTP client.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['WinSCP', 'Martin Prikryl', 'https://winscp.net/', 'https://winscp.net/eng/download.php', 'SFTP, FTP and SCP client for Windows.', 'developer-tools', 'open_source', 'windows', 'Windows'],
        ['PuTTY', 'Simon Tatham', 'https://www.putty.org/', 'https://www.putty.org/', 'SSH and telnet client.', 'developer-tools', 'open_source', 'windows', 'Windows'],
        ['Wireshark', 'Wireshark Foundation', 'https://www.wireshark.org/', 'https://www.wireshark.org/download.html', 'Network protocol analyzer.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['XAMPP', 'Apache Friends', 'https://www.apachefriends.org/', 'https://www.apachefriends.org/download.html', 'Apache, MySQL, PHP and Perl stack.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['DBeaver', 'DBeaver', 'https://dbeaver.io/', 'https://dbeaver.io/download/', 'Universal database management tool.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['GitHub Desktop', 'GitHub', 'https://desktop.github.com/', 'https://desktop.github.com/', 'Simple Git and GitHub desktop client.', 'developer-tools', 'open_source', 'windows', 'Windows, macOS'],
        ['Sourcetree', 'Atlassian', 'https://www.sourcetreeapp.com/', 'https://www.sourcetreeapp.com/', 'Free Git GUI client.', 'developer-tools', 'free', 'windows', 'Windows, macOS'],
        ['WPS Office', 'Kingsoft', 'https://www.wps.com/', 'https://www.wps.com/download/', 'Office suite compatible with MS Office.', 'office', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['ONLYOFFICE', 'Ascensio System', 'https://www.onlyoffice.com/', 'https://www.onlyoffice.com/download-desktop.aspx', 'Office suite for documents and collaboration.', 'office', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['Sumatra PDF', 'Krzysztof Kowalczyk', 'https://www.sumatrapdfreader.org/', 'https://www.sumatrapdfreader.org/download-free-pdf-viewer', 'Lightweight PDF and eBook reader.', 'pdf-tools', 'open_source', 'windows', 'Windows'],
        ['PDFsam Basic', 'PDFsam', 'https://pdfsam.org/', 'https://pdfsam.org/download-pdfsam-basic/', 'Split, merge and rotate PDF files.', 'pdf-tools', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Calibre', 'Kovid Goyal', 'https://calibre-ebook.com/', 'https://calibre-ebook.com/download', 'E-book management and conversion.', 'office', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Obsidian', 'Obsidian', 'https://obsidian.md/', 'https://obsidian.md/download', 'Markdown knowledge base and note-taking.', 'office', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['Joplin', 'Laurent Cozic', 'https://joplinapp.org/', 'https://joplinapp.org/help/#desktop-applications', 'Open-source note-taking and to-do app.', 'office', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['FreeFileSync', 'FreeFileSync', 'https://freefilesync.org/', 'https://freefilesync.org/download.php', 'Folder comparison and synchronization.', 'backup', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Syncthing', 'Syncthing', 'https://syncthing.net/', 'https://syncthing.net/downloads/', 'Continuous, private file synchronization.', 'backup', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Duplicati', 'Duplicati', 'https://www.duplicati.com/', 'https://www.duplicati.com/download', 'Encrypted, incremental online backups.', 'backup', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['MEGA', 'MEGA', 'https://mega.io/', 'https://mega.io/desktop', 'Secure cloud storage and sync.', 'backup', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['pCloud', 'pCloud', 'https://www.pcloud.com/', 'https://www.pcloud.com/download-free-online-cloud-file-storage.html', 'Cloud storage with desktop sync.', 'backup', 'freemium', 'windows', 'Windows, macOS, Linux'],
        ['uTorrent', 'BitTorrent Inc.', 'https://www.utorrent.com/', 'https://www.utorrent.com/desktop/', 'Popular lightweight BitTorrent client.', 'utilities', 'freemium', 'windows', 'Windows'],
        ['Free Download Manager', 'FreeDownloadManager.org', 'https://www.freedownloadmanager.org/', 'https://www.freedownloadmanager.org/download.htm', 'Download accelerator and manager.', 'utilities', 'free', 'windows', 'Windows, macOS'],
        ['JDownloader', 'AppWork', 'https://jdownloader.org/', 'https://jdownloader.org/download/index', 'Open-source download management tool.', 'utilities', 'free', 'windows', 'Windows, macOS, Linux'],
        ['Transmission', 'Transmission Project', 'https://transmissionbt.com/', 'https://transmissionbt.com/download', 'Lightweight, open-source BitTorrent client.', 'utilities', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Viber', 'Rakuten', 'https://www.viber.com/', 'https://www.viber.com/download/', 'Messaging and calling app.', 'communication', 'free', 'windows', 'Windows, macOS, Linux'],
        ['eM Client', 'eM Client', 'https://www.emclient.com/', 'https://www.emclient.com/download', 'Email, calendar and chat client.', 'communication', 'freemium', 'windows', 'Windows, macOS'],
        ['Element', 'Element', 'https://element.io/', 'https://element.io/download', 'Secure Matrix-based team messenger.', 'communication', 'open_source', 'windows', 'Windows, macOS, Linux'],
        ['Playnite', 'Playnite', 'https://playnite.link/', 'https://playnite.link/', 'Open-source video game library manager.', 'utilities', 'open_source', 'windows', 'Windows'],
    ];

    // -- macOS: Homebrew casks -------------------------------------------------
    private static function homebrew(int $maxNew): array
    {
        $casks = self::cachedJson('homebrew_casks', 'https://formulae.brew.sh/api/cask.json');
        if (!is_array($casks)) {
            return self::zero('homebrew unavailable');
        }
        return self::importSlice('brew_off', $casks, $maxNew, static function ($c) {
            $token = $c['token'] ?? null;
            if (!$token) {
                return null;
            }
            $name = is_array($c['name'] ?? null) ? ($c['name'][0] ?? $token) : ($c['name'] ?? $token);
            return [
                'external_ref'          => 'brew:' . strtolower((string) $token),
                'name'                  => (string) $name,
                'developer_name'        => self::host((string) ($c['homepage'] ?? '')),
                'official_website'      => $c['homepage'] ?? null,
                'official_download_url' => $c['url'] ?? ($c['homepage'] ?? null),
                'short_description'     => str_excerpt((string) ($c['desc'] ?? ''), 300),
                'long_description'      => (string) ($c['desc'] ?? ''),
                'version'               => is_string($c['version'] ?? null) ? $c['version'] : null,
                'price_type'            => null,
                'is_open_source'        => 0,
                'os_slug'               => 'macos',
                'os_label'              => 'macOS',
                'signals'               => ($c['desc'] ?? '') . ' ' . $name,
            ];
        });
    }

    // -- Linux: Flathub --------------------------------------------------------
    private static function flathub(int $maxNew): array
    {
        $apps = self::cachedJson('flathub_apps', 'https://flathub.org/api/v1/apps');
        if (!is_array($apps)) {
            return self::zero('flathub unavailable');
        }
        return self::importSlice('flathub_off', $apps, $maxNew, static function ($a) {
            $id = $a['flatpakAppId'] ?? null;
            if (!$id) {
                return null;
            }
            return [
                'external_ref'          => 'flatpak:' . strtolower((string) $id),
                'name'                  => (string) ($a['name'] ?? $id),
                'developer_name'        => (string) ($a['developerName'] ?? ''),
                'official_website'      => 'https://flathub.org/apps/' . $id,
                'official_download_url' => 'https://flathub.org/apps/' . $id,
                'short_description'     => str_excerpt((string) ($a['summary'] ?? ''), 300),
                'long_description'      => (string) ($a['summary'] ?? ''),
                'version'               => is_string($a['currentReleaseVersion'] ?? null) ? $a['currentReleaseVersion'] : null,
                'price_type'            => 'free',
                'is_open_source'        => 0,
                'os_slug'               => 'linux',
                'os_label'              => 'Linux',
                'signals'               => ($a['summary'] ?? '') . ' ' . ($a['name'] ?? ''),
                'logo'                  => $a['iconDesktopUrl'] ?? null,
            ];
        });
    }

    // -- Android: F-Droid ------------------------------------------------------
    private static function fdroid(int $maxNew): array
    {
        $index = self::cachedJson('fdroid_index', 'https://f-droid.org/repo/index-v1.json', 86400, 60);
        $apps = is_array($index['apps'] ?? null) ? $index['apps'] : null;
        if (!is_array($apps)) {
            return self::zero('f-droid unavailable');
        }
        return self::importSlice('fdroid_off', $apps, $maxNew, static function ($a) {
            $pkg = $a['packageName'] ?? null;
            if (!$pkg) {
                return null;
            }
            $name = $a['name'] ?? ($a['localized']['en-US']['name'] ?? $pkg);
            $summary = $a['summary'] ?? ($a['localized']['en-US']['summary'] ?? '');
            return [
                'external_ref'          => 'fdroid:' . strtolower((string) $pkg),
                'name'                  => (string) $name,
                'developer_name'        => (string) ($a['authorName'] ?? ''),
                'official_website'      => $a['webSite'] ?: ($a['sourceCode'] ?? 'https://f-droid.org/packages/' . $pkg),
                'official_download_url' => 'https://f-droid.org/packages/' . $pkg,
                'short_description'     => str_excerpt((string) $summary, 300),
                'long_description'      => (string) $summary,
                'version'               => is_string($a['suggestedVersionName'] ?? null) ? $a['suggestedVersionName'] : null,
                'license_type'          => $a['license'] ?? null,
                'price_type'            => 'open_source',
                'is_open_source'        => 1,
                'os_slug'               => 'android',
                'os_label'              => 'Android',
                'signals'               => $summary . ' ' . implode(' ', (array) ($a['categories'] ?? [])),
            ];
        });
    }

    // -- shared import ---------------------------------------------------------

    /**
     * @param string $cursorKey settings key holding the offset into $list
     * @param array  $list      full catalog array
     * @param int    $maxNew    max new records to publish this run
     * @param callable $mapper  fn(item): ?array normalized dto
     */
    private static function importSlice(string $cursorKey, array $list, int $maxNew, callable $mapper): array
    {
        $off = self::intSetting($cursorKey, 0);
        $count = count($list);
        if ($off >= $count) {
            return ['created' => 0, 'skipped' => 0, 'scanned' => 0, 'message' => 'catalog complete'];
        }

        $created = $skipped = $scanned = 0;
        $i = $off;
        // Scan forward until we publish $maxNew new items or reach the end.
        for (; $i < $count && $created < $maxNew; $i++) {
            $scanned++;
            $dto = $mapper($list[$i]);
            if ($dto === null) {
                $skipped++;
                continue;
            }
            try {
                self::store($dto) ? $created++ : $skipped++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }
        self::setSetting($cursorKey, (string) $i);

        return ['created' => $created, 'skipped' => $skipped, 'scanned' => $scanned,
                'message' => 'imported ' . $created];
    }

    /** @return bool true if newly created */
    private static function store(array $d): bool
    {
        if (empty($d['name']) || empty($d['external_ref'])) {
            return false;
        }
        Dedupe::ensureSchema();
        if (Database::scalar('SELECT id FROM software WHERE external_ref = :r', ['r' => $d['external_ref']])) {
            return false;
        }

        // Same product from a different source? Enrich the existing record with
        // this source's extra details + operating system instead of duplicating.
        $existing = Dedupe::findExisting((string) $d['name'], true);
        if ($existing !== null) {
            Dedupe::enrich($existing, $d, $d['os_slug'] ?? null, $d['os_label'] ?? null);
            return false;
        }

        $categoryId = null;
        if (!empty($d['category_slug'])) {
            $cid = Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => $d['category_slug']]);
            $categoryId = $cid ? (int) $cid : null;
        }
        if ($categoryId === null) {
            $categoryId = Classifier::detectCategory($d['name'], (string) ($d['signals'] ?? '')) ?? self::defaultCategory();
        }

        $record = [
            'name'                  => $d['name'],
            'developer_name'        => $d['developer_name'] ?: null,
            'official_website'      => $d['official_website'] ?: null,
            'official_download_url' => $d['official_download_url'] ?: null,
            'short_description'     => $d['short_description'] ?: null,
            'long_description'      => $d['long_description'] ?: null,
            'version'               => $d['version'] ?: null,
            'license_type'          => $d['license_type'] ?? null,
            'price_type'            => $d['price_type'] ?? null,
            'is_open_source'        => (int) ($d['is_open_source'] ?? 0),
            'operating_system'      => $d['os_label'] ?? null,
            'category_id'           => $categoryId,
            'logo'                  => $d['logo'] ?? null,
            'source_type'           => explode(':', $d['external_ref'])[0],
            'source_url'            => $d['official_website'] ?: null,
            'external_ref'          => $d['external_ref'],
            'dedupe_key'            => Dedupe::key((string) $d['name']),
            'last_checked_at'       => gmdate('Y-m-d H:i:s'),
            'discovered_at'         => gmdate('Y-m-d H:i:s'),
            'last_updated'          => gmdate('Y-m-d'),
        ];
        $record['trust_score']         = TrustScore::compute($record);
        $record['quality_score']       = TrustScore::quality($record);
        $record['verification_status'] = $record['trust_score'] >= 70 ? 'verified' : 'review';
        $record['status']              = 'published';
        $record['slug']                = self::uniqueSlug(\slugify((string) $d['name']));

        $id = Database::insert('software', array_filter($record, static fn($v) => $v !== null));

        // OS mapping.
        $osId = Database::scalar('SELECT id FROM operating_systems WHERE slug = :s', ['s' => $d['os_slug'] ?? '']);
        if ($osId) {
            try {
                Database::run('INSERT IGNORE INTO software_operating_systems (software_id, os_id) VALUES (:s, :o)',
                    ['s' => $id, 'o' => (int) $osId]);
            } catch (\Throwable $e) {
            }
        }
        Seo::generateForSoftware($id);
        return true;
    }

    // -- helpers ---------------------------------------------------------------

    private static function cachedJson(string $key, string $url, int $ttl = 86400, int $timeout = 40): ?array
    {
        $dir = Config::get('paths.storage') . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . preg_replace('/[^a-z0-9]/i', '_', $key) . '.json';

        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $resp = Http::get($url, ['Accept: application/json'], $timeout);
        if ($resp['status'] === 200 && $resp['body'] !== '') {
            $data = json_decode($resp['body'], true);
            if (is_array($data)) {
                @file_put_contents($file, $resp['body']);
                return $data;
            }
        }
        // Fall back to any stale cache.
        if (is_file($file)) {
            $stale = json_decode((string) file_get_contents($file), true);
            if (is_array($stale)) {
                return $stale;
            }
        }
        return null;
    }

    private static function defaultCategory(): ?int
    {
        $id = Database::scalar('SELECT id FROM categories WHERE slug = :s', ['s' => 'utilities']);
        return $id ? (int) $id : null;
    }

    private static function uniqueSlug(string $base): string
    {
        $base = $base ?: 'app';
        $slug = $base;
        $i = 2;
        while (Database::scalar('SELECT id FROM software WHERE slug = :s', ['s' => $slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private static function host(string $url): string
    {
        $h = parse_url($url, PHP_URL_HOST);
        return $h ? preg_replace('/^www\./', '', (string) $h) : '';
    }

    private static function intSetting(string $k, int $default): int
    {
        $v = Database::scalar('SELECT `value` FROM settings WHERE `key` = :k', ['k' => $k]);
        return is_numeric($v) ? (int) $v : $default;
    }

    private static function setSetting(string $k, string $v): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`, `group`, `type`) VALUES (:k, :v, "catalog", "int")
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['k' => $k, 'v' => $v]
        );
    }

    private static function zero(string $msg): array
    {
        return ['created' => 0, 'skipped' => 0, 'scanned' => 0, 'message' => $msg];
    }
}
