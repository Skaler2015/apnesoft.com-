-- =============================================================================
-- SoftwareHub — seed / reference data
-- Reference tables (OS, categories, cron jobs, settings) + a small set of real,
-- verifiable open-source apps so the site is populated out of the box.
-- Version/metadata here are seed placeholders and are refreshed by the crawler.
-- =============================================================================

SET NAMES utf8mb4;

-- --- Settings ----------------------------------------------------------------
INSERT INTO settings (`key`, `value`, `group`, `type`, is_public) VALUES
 ('site_name', 'SoftwareHub', 'branding', 'string', 1),
 ('tagline', 'Find the Right Software for Your PC', 'branding', 'string', 1),
 ('primary_color', '#4f46e5', 'branding', 'color', 1),
 ('secondary_color', '#0ea5e9', 'branding', 'color', 1),
 ('footer_text', 'Discover, compare and download trusted software from official sources.', 'branding', 'string', 1),
 ('threshold_auto_publish', '90', 'automation', 'int', 0),
 ('threshold_conditional', '70', 'automation', 'int', 0),
 ('threshold_review', '40', 'automation', 'int', 0)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- --- Operating systems -------------------------------------------------------
INSERT INTO operating_systems (name, slug, icon, sort_order) VALUES
 ('Windows', 'windows', '🪟', 1),
 ('macOS', 'macos', '', 2),
 ('Linux', 'linux', '🐧', 3),
 ('Android', 'android', '🤖', 4)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- --- Categories --------------------------------------------------------------
INSERT INTO categories (name, slug, description, icon, is_trending, sort_order) VALUES
 ('PDF Tools', 'pdf-tools', 'Read, edit, convert and manage PDF documents.', '📄', 1, 1),
 ('Video Editing', 'video-editing', 'Edit, cut and produce video.', '🎬', 1, 2),
 ('Photo Editing', 'photo-editing', 'Edit and retouch images.', '🖼️', 1, 3),
 ('Screen Recording', 'screen-recording', 'Capture your screen and stream.', '🎥', 1, 4),
 ('Security', 'security', 'Antivirus, VPN and privacy tools.', '🛡️', 1, 5),
 ('File Tools', 'file-tools', 'Compression, archiving and file management.', '🗜️', 0, 6),
 ('Browsers', 'browsers', 'Web browsers.', '🌐', 1, 7),
 ('Developer Tools', 'developer-tools', 'Editors, IDEs and dev utilities.', '⌨️', 1, 8),
 ('Office', 'office', 'Documents, spreadsheets and presentations.', '📊', 0, 9),
 ('Backup', 'backup', 'Backup and sync tools.', '💾', 0, 10),
 ('Remote Tools', 'remote-tools', 'Remote desktop and access.', '🖥️', 0, 11),
 ('Media Players', 'media-players', 'Audio and video players.', '▶️', 1, 12),
 ('Utilities', 'utilities', 'System utilities and cleaners.', '🧰', 0, 13),
 ('Communication', 'communication', 'Chat and messaging apps.', '💬', 0, 14),
 ('Productivity', 'productivity', 'Notes and productivity apps.', '✅', 0, 15)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- --- Cron jobs registry ------------------------------------------------------
INSERT INTO cron_jobs (`key`, name, description, schedule, enabled) VALUES
 ('discover', 'Discovery', 'Run all due sources through the discovery pipeline', 'every 6h', 1),
 ('github_sync', 'GitHub Sync', 'Sync GitHub API sources', 'every 6h', 1),
 ('winget_sync', 'Winget Sync', 'Sync winget package metadata', 'daily', 1),
 ('rss_sync', 'RSS Sync', 'Fetch RSS/Atom feeds', 'every 3h', 1),
 ('version_check', 'Version Checker', 'Detect new versions for existing software', 'every 6h', 1),
 ('link_check', 'Link Checker', 'Verify download/official URLs', 'daily', 1),
 ('seo_update', 'SEO Generator', 'Regenerate SEO metadata', 'daily', 1),
 ('sitemap', 'Sitemap Generator', 'Rebuild sitemaps', 'daily', 1),
 ('cleanup', 'Cleanup', 'Prune old logs and analytics', 'weekly', 1),
 ('backup', 'Database Backup', 'Backup the database', 'daily', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- --- Example sources (disabled by default; enable + configure in admin) -------
INSERT INTO software_sources (name, source_type, source_url, config, status, priority, trust_score, crawl_frequency) VALUES
 ('GitHub — Popular OSS', 'github_api', 'https://github.com',
  '{"repos":["videolan/vlc","obsproject/obs-studio","BleachBit/BleachBit","gimp/gimp"],"min_stars":100}',
  'paused', 8, 85, 360),
 ('Winget — Common Apps', 'winget', 'https://github.com/microsoft/winget-pkgs',
  '{"packages":["Mozilla.Firefox","VideoLAN.VLC"]}', 'paused', 6, 75, 1440)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- --- Sample software (real, verifiable open-source apps) ----------------------
-- Published so the site is populated. Versions are seed placeholders.
INSERT INTO software
 (name, slug, developer_name, developer_website, official_website, official_download_url,
  short_description, long_description, version, release_date, last_updated, license_type,
  price_type, is_open_source, file_size, architecture, operating_system, min_ram_mb,
  minimum_requirements, category_id, logo, source_type, source_url, external_ref,
  trust_score, quality_score, verification_status, status, discovered_at, last_checked_at)
VALUES
 ('VLC Media Player', 'vlc-media-player', 'VideoLAN', 'https://www.videolan.org', 'https://www.videolan.org/vlc/',
  'https://www.videolan.org/vlc/', 'Free, open-source cross-platform media player that plays most formats.',
  'VLC is a free and open source cross-platform multimedia player that plays most multimedia files as well as DVDs, Audio CDs, VCDs, and various streaming protocols.',
  '3.0', '2024-01-01', NOW(), 'GPL-2.0', 'open_source', 1, '~40 MB', 'x64', 'Windows, macOS, Linux', 512,
  '512 MB RAM, any modern CPU.', (SELECT id FROM categories WHERE slug='media-players'),
  NULL, 'github_api', 'https://github.com/videolan/vlc', 'github:videolan/vlc',
  95, 85, 'verified', 'published', NOW(), NOW()),

 ('OBS Studio', 'obs-studio', 'OBS Project', 'https://obsproject.com', 'https://obsproject.com',
  'https://obsproject.com/download', 'Free and open-source software for video recording and live streaming.',
  'OBS Studio is free and open source software for video recording and live streaming. Capture, compose scenes, and stream to major platforms.',
  '30', '2024-01-01', NOW(), 'GPL-2.0', 'open_source', 1, '~120 MB', 'x64', 'Windows, macOS, Linux', 4096,
  '4 GB RAM, DirectX 10.1 compatible GPU.', (SELECT id FROM categories WHERE slug='screen-recording'),
  NULL, 'github_api', 'https://github.com/obsproject/obs-studio', 'github:obsproject/obs-studio',
  95, 88, 'verified', 'published', NOW(), NOW()),

 ('GIMP', 'gimp', 'The GIMP Team', 'https://www.gimp.org', 'https://www.gimp.org', 'https://www.gimp.org/downloads/',
  'Free and open-source raster graphics editor for photo retouching and image editing.',
  'GIMP is a cross-platform image editor available for GNU/Linux, macOS, Windows and more. It offers tools for photo retouching, image composition and image authoring.',
  '2.10', '2024-01-01', NOW(), 'GPL-3.0', 'open_source', 1, '~250 MB', 'x64', 'Windows, macOS, Linux', 2048,
  '2 GB RAM recommended.', (SELECT id FROM categories WHERE slug='photo-editing'),
  NULL, 'website', 'https://www.gimp.org', 'web:gimp.org',
  90, 82, 'verified', 'published', NOW(), NOW()),

 ('Mozilla Firefox', 'mozilla-firefox', 'Mozilla', 'https://www.mozilla.org', 'https://www.mozilla.org/firefox/',
  'https://www.mozilla.org/firefox/download/', 'Fast, private and open-source web browser.',
  'Firefox is a free and open-source web browser developed by Mozilla, focused on privacy and standards support.',
  '120', '2024-01-01', NOW(), 'MPL-2.0', 'open_source', 1, '~60 MB', 'x64', 'Windows, macOS, Linux', 2048,
  '2 GB RAM.', (SELECT id FROM categories WHERE slug='browsers'),
  NULL, 'website', 'https://www.mozilla.org', 'web:mozilla.org',
  92, 84, 'verified', 'published', NOW(), NOW()),

 ('7-Zip', '7-zip', 'Igor Pavlov', 'https://www.7-zip.org', 'https://www.7-zip.org', 'https://www.7-zip.org/download.html',
  'Free and open-source file archiver with a high compression ratio.',
  '7-Zip is a file archiver with a high compression ratio, supporting many formats including its own 7z format.',
  '23', '2024-01-01', NOW(), 'LGPL', 'open_source', 1, '~1.5 MB', 'x64', 'Windows', 256,
  'Runs on virtually any Windows PC.', (SELECT id FROM categories WHERE slug='file-tools'),
  NULL, 'website', 'https://www.7-zip.org', 'web:7-zip.org',
  88, 80, 'verified', 'published', NOW(), NOW()),

 ('BleachBit', 'bleachbit', 'BleachBit', 'https://www.bleachbit.org', 'https://www.bleachbit.org',
  'https://www.bleachbit.org/download', 'Free disk cleaner and privacy tool for low-end and older PCs.',
  'BleachBit frees disk space and maintains privacy by clearing cache, deleting cookies, and wiping temporary files. Lightweight and open source.',
  '4', '2024-01-01', NOW(), 'GPL-3.0', 'open_source', 1, '~15 MB', 'x64', 'Windows, Linux', 512,
  '512 MB RAM — great for low-end PCs.', (SELECT id FROM categories WHERE slug='utilities'),
  NULL, 'github_api', 'https://github.com/bleachbit/bleachbit', 'github:bleachbit/bleachbit',
  86, 78, 'verified', 'published', NOW(), NOW()),

 ('Krita', 'krita', 'Krita Foundation', 'https://krita.org', 'https://krita.org', 'https://krita.org/en/download/',
  'Free and open-source digital painting and illustration software.',
  'Krita is a professional free and open source painting program made by artists for artists, ideal for concept art, illustration and textures.',
  '5', '2024-01-01', NOW(), 'GPL-3.0', 'open_source', 1, '~150 MB', 'x64', 'Windows, macOS, Linux', 4096,
  '4 GB RAM recommended.', (SELECT id FROM categories WHERE slug='photo-editing'),
  NULL, 'website', 'https://krita.org', 'web:krita.org',
  87, 80, 'verified', 'published', NOW(), NOW()),

 ('Shotcut', 'shotcut', 'Meltytech, LLC', 'https://www.shotcut.org', 'https://www.shotcut.org',
  'https://www.shotcut.org/download/', 'Free, open-source, cross-platform video editor.',
  'Shotcut is a free, open source, cross-platform video editor with a wide format support and intuitive interface.',
  '24', '2024-01-01', NOW(), 'GPL-3.0', 'open_source', 1, '~90 MB', 'x64', 'Windows, macOS, Linux', 4096,
  '4 GB RAM.', (SELECT id FROM categories WHERE slug='video-editing'),
  NULL, 'website', 'https://www.shotcut.org', 'web:shotcut.org',
  85, 79, 'verified', 'published', NOW(), NOW());

-- --- OS mappings -------------------------------------------------------------
INSERT IGNORE INTO software_operating_systems (software_id, os_id)
SELECT s.id, o.id FROM software s JOIN operating_systems o
WHERE (s.slug IN ('vlc-media-player','obs-studio','gimp','mozilla-firefox','krita','shotcut') AND o.slug IN ('windows','macos','linux'))
   OR (s.slug = '7-zip' AND o.slug = 'windows')
   OR (s.slug = 'bleachbit' AND o.slug IN ('windows','linux'));

-- --- Features / pros for VLC (illustrative) ----------------------------------
INSERT INTO software_features (software_id, `type`, label, sort_order)
SELECT id, 'feature', label, n FROM software CROSS JOIN (
  SELECT 'Plays most audio and video formats' AS label, 1 AS n UNION ALL
  SELECT 'No spyware, ads or user tracking', 2 UNION ALL
  SELECT 'Streaming and network playback', 3 UNION ALL
  SELECT 'Cross-platform', 4
) f WHERE slug = 'vlc-media-player';

INSERT INTO software_features (software_id, `type`, label, sort_order)
SELECT id, 'pro', label, n FROM software CROSS JOIN (
  SELECT 'Completely free and open source' AS label, 1 AS n UNION ALL
  SELECT 'Extremely wide format support', 2
) f WHERE slug = 'vlc-media-player';

-- --- Version rows for the seeded software ------------------------------------
INSERT IGNORE INTO software_versions (software_id, version, normalized, release_date, download_url, is_current)
SELECT id, version, version, release_date, official_download_url, 1 FROM software WHERE version IS NOT NULL;

-- --- Some alternatives (bidirectional-ish) -----------------------------------
INSERT IGNORE INTO software_alternatives (software_id, alternative_id, similarity, reason)
SELECT a.id, b.id, 70, 'Same category'
FROM software a JOIN software b ON a.category_id = b.category_id AND a.id <> b.id
WHERE a.category_id IS NOT NULL;
