-- =============================================================================
-- SoftwareHub — Smart Software Discovery & Download Platform
-- Normalized MySQL 8+ schema
-- Engine: InnoDB, Charset: utf8mb4
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Settings (branding, thresholds, toggles) — key/value store
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`         VARCHAR(150) NOT NULL,
    `value`       LONGTEXT NULL,
    `group`       VARCHAR(80) NOT NULL DEFAULT 'general',
    `type`        VARCHAR(30) NOT NULL DEFAULT 'string', -- string|int|bool|json|text|color
    is_public     TINYINT(1) NOT NULL DEFAULT 0,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (`key`),
    KEY idx_settings_group (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Operating systems
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS operating_systems (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80) NOT NULL,
    slug       VARCHAR(80) NOT NULL,
    icon       VARCHAR(120) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_os_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Categories & subcategories (self-referencing parent_id)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id      INT UNSIGNED NULL,
    name           VARCHAR(120) NOT NULL,
    slug           VARCHAR(140) NOT NULL,
    description    TEXT NULL,
    icon           VARCHAR(120) NULL,
    meta_title     VARCHAR(200) NULL,
    meta_desc      VARCHAR(320) NULL,
    sort_order     INT NOT NULL DEFAULT 0,
    is_trending    TINYINT(1) NOT NULL DEFAULT 0,
    status         VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cat_slug (slug),
    KEY idx_cat_parent (parent_id),
    CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Software sources (Source Manager)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_sources (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(160) NOT NULL,
    source_type     VARCHAR(40) NOT NULL, -- github_api|github_webhook|rss|atom|website|public_api|package_repo|winget|manual
    source_url      VARCHAR(500) NULL,
    api_url         VARCHAR(500) NULL,
    api_key_enc     TEXT NULL,             -- encrypted at rest
    auth_method     VARCHAR(40) NULL,      -- none|token|basic|header
    config          JSON NULL,             -- type-specific settings (e.g. github repo list)
    status          VARCHAR(20) NOT NULL DEFAULT 'active', -- active|paused|disabled
    priority        INT NOT NULL DEFAULT 5,
    trust_score     TINYINT UNSIGNED NOT NULL DEFAULT 50,
    crawl_frequency INT NOT NULL DEFAULT 1440, -- minutes
    last_sync       DATETIME NULL,
    next_sync       DATETIME NULL,
    error_count     INT NOT NULL DEFAULT 0,
    last_error      TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_src_status (status),
    KEY idx_src_type (source_type),
    KEY idx_src_next_sync (next_sync)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Software (main table)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                  VARCHAR(200) NOT NULL,
    slug                  VARCHAR(220) NOT NULL,
    developer_name        VARCHAR(200) NULL,
    developer_website     VARCHAR(500) NULL,
    official_website      VARCHAR(500) NULL,
    official_download_url VARCHAR(700) NULL,
    short_description     VARCHAR(320) NULL,
    long_description      MEDIUMTEXT NULL,
    version               VARCHAR(80) NULL,
    previous_version      VARCHAR(80) NULL,
    release_date          DATE NULL,
    last_updated          DATETIME NULL,
    license_type          VARCHAR(80) NULL,   -- MIT, GPL, Proprietary, Freeware...
    price_type            VARCHAR(30) NULL,   -- free|paid|freemium|open_source|trial
    is_open_source        TINYINT(1) NOT NULL DEFAULT 0,
    file_size             VARCHAR(60) NULL,
    architecture          VARCHAR(80) NULL,   -- x64, x86, arm64, universal
    operating_system      VARCHAR(160) NULL,  -- denormalized quick label
    min_ram_mb            INT UNSIGNED NULL,  -- for low-end PC filtering
    minimum_requirements  TEXT NULL,
    category_id           INT UNSIGNED NULL,
    subcategory_id        INT UNSIGNED NULL,
    logo                  VARCHAR(300) NULL,
    changelog             MEDIUMTEXT NULL,
    stars                 INT UNSIGNED NULL,
    forks                 INT UNSIGNED NULL,
    source_id             BIGINT UNSIGNED NULL,
    source_type           VARCHAR(40) NULL,
    source_url            VARCHAR(700) NULL,
    external_ref          VARCHAR(300) NULL,  -- e.g. github owner/repo, winget package id
    dedupe_key            VARCHAR(160) NOT NULL DEFAULT '', -- normalized name for cross-source de-duplication
    ai_enhanced_at        DATETIME NULL,      -- last time AI enriched this record
    video_url             VARCHAR(500) NULL,  -- optional demo / YouTube video
    auto_update           TINYINT(1) NOT NULL DEFAULT 0, -- auto-bump version when a newer one is found
    trust_score           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    quality_score         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    verification_status   VARCHAR(20) NOT NULL DEFAULT 'unverified', -- verified|review|unverified
    auto_publish          TINYINT(1) NOT NULL DEFAULT 0,
    status                VARCHAR(20) NOT NULL DEFAULT 'draft', -- published|draft|review|rejected|disabled
    views                 BIGINT UNSIGNED NOT NULL DEFAULT 0,
    download_clicks       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    discovered_at         DATETIME NULL,
    last_checked_at       DATETIME NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_software_slug (slug),
    KEY idx_software_status (status),
    KEY idx_software_category (category_id),
    KEY idx_software_subcategory (subcategory_id),
    KEY idx_software_price (price_type),
    KEY idx_software_opensource (is_open_source),
    KEY idx_software_ram (min_ram_mb),
    KEY idx_software_updated (last_updated),
    KEY idx_software_discovered (discovered_at),
    KEY idx_software_trust (trust_score),
    KEY idx_software_source (source_id),
    KEY idx_software_extref (external_ref),
    KEY idx_software_dedupe (dedupe_key),
    FULLTEXT KEY ft_software (name, short_description, long_description),
    CONSTRAINT fk_software_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_software_source FOREIGN KEY (source_id) REFERENCES software_sources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Software <-> OS (many-to-many)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_operating_systems (
    software_id BIGINT UNSIGNED NOT NULL,
    os_id       INT UNSIGNED NOT NULL,
    PRIMARY KEY (software_id, os_id),
    KEY idx_sos_os (os_id),
    CONSTRAINT fk_sos_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE,
    CONSTRAINT fk_sos_os FOREIGN KEY (os_id) REFERENCES operating_systems (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Versions history
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_versions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id   BIGINT UNSIGNED NOT NULL,
    version       VARCHAR(80) NOT NULL,
    normalized    VARCHAR(120) NULL,      -- normalized semver for comparison
    release_date  DATE NULL,
    download_url  VARCHAR(700) NULL,
    file_size     VARCHAR(60) NULL,
    changelog     MEDIUMTEXT NULL,
    is_current    TINYINT(1) NOT NULL DEFAULT 0,
    source_id     BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ver_software (software_id),
    KEY idx_ver_current (software_id, is_current),
    UNIQUE KEY uq_ver_software_version (software_id, version),
    CONSTRAINT fk_ver_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Downloads (outbound click tracking)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_downloads (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id   BIGINT UNSIGNED NOT NULL,
    source        VARCHAR(80) NULL,   -- official|github|winget
    destination   VARCHAR(700) NULL,
    device        VARCHAR(40) NULL,   -- desktop|mobile|tablet (coarse)
    referrer      VARCHAR(300) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dl_software (software_id),
    KEY idx_dl_created (created_at),
    CONSTRAINT fk_dl_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Screenshots
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_screenshots (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    url         VARCHAR(500) NOT NULL,
    caption     VARCHAR(200) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_ss_software (software_id),
    CONSTRAINT fk_ss_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Features / pros / cons (typed rows)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_features (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id BIGINT UNSIGNED NOT NULL,
    `type`      VARCHAR(20) NOT NULL DEFAULT 'feature', -- feature|pro|con
    label       VARCHAR(300) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_feat_software (software_id, `type`),
    CONSTRAINT fk_feat_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tags
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(90) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS software_tags (
    software_id BIGINT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (software_id, tag_id),
    KEY idx_st_tag (tag_id),
    CONSTRAINT fk_st_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE,
    CONSTRAINT fk_st_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Alternatives (directional relation with similarity score)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_alternatives (
    software_id     BIGINT UNSIGNED NOT NULL,
    alternative_id  BIGINT UNSIGNED NOT NULL,
    similarity      TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0-100
    reason          VARCHAR(200) NULL,
    PRIMARY KEY (software_id, alternative_id),
    KEY idx_alt_alt (alternative_id),
    CONSTRAINT fk_alt_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE,
    CONSTRAINT fk_alt_alt FOREIGN KEY (alternative_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Comparisons (cached, indexable when meaningful)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS software_comparisons (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug           VARCHAR(300) NOT NULL,   -- vlc-vs-potplayer
    software_ids   VARCHAR(200) NOT NULL,   -- csv of ids (2-4)
    title          VARCHAR(300) NULL,
    is_indexable   TINYINT(1) NOT NULL DEFAULT 0,
    views          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cmp_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Update history (version bump audit)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS update_history (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id   BIGINT UNSIGNED NOT NULL,
    old_version   VARCHAR(80) NULL,
    new_version   VARCHAR(80) NULL,
    release_date  DATE NULL,
    source_id     BIGINT UNSIGNED NULL,
    detail        TEXT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_uh_software (software_id),
    KEY idx_uh_created (created_at),
    CONSTRAINT fk_uh_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Crawler / verification logs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crawler_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_id    BIGINT UNSIGNED NULL,
    job          VARCHAR(60) NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'ok', -- ok|error|partial
    processed    INT NOT NULL DEFAULT 0,
    created      INT NOT NULL DEFAULT 0,
    updated      INT NOT NULL DEFAULT 0,
    skipped      INT NOT NULL DEFAULT 0,
    failed       INT NOT NULL DEFAULT 0,
    message      TEXT NULL,
    started_at   DATETIME NULL,
    finished_at  DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cl_source (source_id),
    KEY idx_cl_job (job),
    KEY idx_cl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verification_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id   BIGINT UNSIGNED NULL,
    check_type    VARCHAR(40) NULL,   -- link|https|version|source
    url           VARCHAR(700) NULL,
    http_status   INT NULL,
    result        VARCHAR(20) NULL,   -- working|redirect|unavailable|broken
    detail        TEXT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vl_software (software_id),
    KEY idx_vl_result (result),
    CONSTRAINT fk_vl_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- SEO metadata (per entity)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seo_metadata (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type   VARCHAR(30) NOT NULL, -- software|category|compare|page
    entity_id     BIGINT UNSIGNED NOT NULL,
    title         VARCHAR(200) NULL,
    description   VARCHAR(320) NULL,
    canonical     VARCHAR(500) NULL,
    og_title      VARCHAR(200) NULL,
    og_description VARCHAR(320) NULL,
    og_image      VARCHAR(500) NULL,
    is_indexable  TINYINT(1) NOT NULL DEFAULT 1,
    structured    JSON NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seo_entity (entity_type, entity_id),
    KEY idx_seo_indexable (is_indexable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Duplicate review queue
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS duplicate_candidates (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    software_id   BIGINT UNSIGNED NOT NULL,
    match_id      BIGINT UNSIGNED NOT NULL,
    confidence    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reason        VARCHAR(200) NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|merged|ignored
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dup_status (status),
    CONSTRAINT fk_dup_software FOREIGN KEY (software_id) REFERENCES software (id) ON DELETE CASCADE,
    CONSTRAINT fk_dup_match FOREIGN KEY (match_id) REFERENCES software (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Admins, roles, audit
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(30) NOT NULL DEFAULT 'super_admin', -- super_admin|editor|reviewer|seo_manager|automation_manager
    status         VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at  DATETIME NULL,
    failed_logins  INT NOT NULL DEFAULT 0,
    locked_until   DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id    BIGINT UNSIGNED NULL,
    action      VARCHAR(120) NOT NULL,
    entity      VARCHAR(60) NULL,
    entity_id   BIGINT UNSIGNED NULL,
    detail      TEXT NULL,
    ip          VARCHAR(64) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alog_admin (admin_id),
    KEY idx_alog_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional public users (future ratings / saved lists)
CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120) NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Automation jobs registry + notifications
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cron_jobs (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`          VARCHAR(60) NOT NULL,
    name           VARCHAR(120) NOT NULL,
    description    VARCHAR(300) NULL,
    schedule       VARCHAR(60) NULL,   -- human-friendly (e.g. "every 6h")
    enabled        TINYINT(1) NOT NULL DEFAULT 1,
    status         VARCHAR(20) NOT NULL DEFAULT 'idle', -- idle|running|error
    last_run_at    DATETIME NULL,
    next_run_at    DATETIME NULL,
    last_duration  INT NULL,           -- seconds
    last_processed INT NULL,
    last_errors    INT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cron_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`      VARCHAR(40) NOT NULL, -- new_software|new_version|broken_link|source_failure|duplicate|review|sitemap|backup
    title       VARCHAR(200) NOT NULL,
    body        TEXT NULL,
    level       VARCHAR(20) NOT NULL DEFAULT 'info', -- info|warning|error|success
    entity      VARCHAR(60) NULL,
    entity_id   BIGINT UNSIGNED NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_read (is_read),
    KEY idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Search / analytics (privacy-conscious, no PII)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS search_queries (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    query       VARCHAR(200) NOT NULL,
    results     INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sq_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_views (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    path        VARCHAR(300) NOT NULL,
    entity_type VARCHAR(30) NULL,
    entity_id   BIGINT UNSIGNED NULL,
    device      VARCHAR(40) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pv_created (created_at),
    KEY idx_pv_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip          VARCHAR(64) NOT NULL,
    email       VARCHAR(190) NULL,
    success     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
