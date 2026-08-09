# SoftwareHub — Smart Software Discovery & Download Platform

A production-oriented, automation-first software discovery platform built in
**plain PHP 8.2+ and MySQL 8+** — no framework, no runtime Composer dependencies,
so it runs on ordinary shared hosting and migrates cleanly to a VPS.

It is **not** a static software list. It automatically discovers software from
configured sources (GitHub, Winget, RSS/Atom, official websites), detects new
versions, scores trust, decides what to auto-publish vs. send to review,
generates SEO metadata and sitemaps, tracks broken links, and surfaces it all
through a modern SaaS-style front end with search, filters, comparison,
alternatives and an interactive Software Finder.

> **Ethics & safety by design.** The platform links to **official / authorized
> sources only** — it never hosts or distributes cracked, pirated or modified
> software, never makes absolute security claims ("100% virus free"), and never
> invents facts (missing data shows *Not specified* or routes to review).

---

## Quick start

```bash
# 1. Configure environment
cp .env.example .env
#    edit .env — set APP_URL, APP_KEY (long random string) and DB_* credentials

# 2. Create the database, then install schema + seed + reference data
php bin/install.php --admin      # prompts to create your first super admin
#    (omit --admin to install without creating an admin; add one later)

# 3. Point your web server document root at /public
#    Apache: /public/.htaccess handles routing.
#    Nginx: try_files $uri /index.php;  (see deploy notes below)

# 4. Install the cron jobs
crontab deploy/crontab.example   # after editing the PHP/APP paths inside it
```

Then visit `/` for the site and `/admin/login` for the admin panel.

### Nginx location block

```nginx
root /var/www/softwarehub/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.2-fpm.sock;
                    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
location ~ /\.(?!well-known) { deny all; }
```

---

## Architecture

```
apnesoft.com-/
├── public/                 # web root (front controller + assets + generated sitemaps)
│   ├── index.php           # single entry point → Router
│   ├── .htaccess           # Apache routing, gzip, caching, hardening
│   └── assets/{css,js,img}
├── app/
│   ├── bootstrap.php       # autoloader, config, error handling, helpers
│   ├── routes.php          # all route definitions
│   ├── Core/               # Config, Database (PDO), Router, Request, Response,
│   │                       # View, Session, Csrf, Crypto, Auth, Settings, Logger
│   ├── Controllers/        # public + Admin/ controllers
│   ├── Models/             # Software, Category, Source, Notification (query gateways)
│   ├── Services/           # the automation engine (see below)
│   ├── Support/            # Http (cURL), Version (semver), Lock (cron mutex), helpers
│   └── Views/              # plain-PHP templates (layouts, partials, pages, admin)
├── cron/                   # one script per job, CLI-only, lock-guarded
├── database/               # schema.sql, seed.sql
├── bin/install.php         # installer / migrator
├── deploy/crontab.example  # ready-to-edit crontab
└── storage/                # logs, cache, locks, backups (writable, git-ignored)
```

### The automation pipeline (`app/Services`)

```
Source → fetch → parse → normalize (DTO) → Ingest
                                              ├── DuplicateDetector  (merge / review / new)
                                              ├── Version::isNewer   (semver-aware bump)
                                              ├── TrustScore         (0–100, verified signals)
                                              ├── Auto-publish rules (thresholds)
                                              ├── Seo::generate      (metadata + schema.org)
                                              └── Notification        (admin alerts)
```

| Service | Responsibility |
|---|---|
| `DiscoveryEngine` | Orchestrates all due sources, dispatches by type, aggregates stats |
| `GitHubService` | GitHub REST: repo + latest release/tags, quality gates (stars, not archived) |
| `WingetService` | winget-pkgs package metadata as an additional discovery signal |
| `RssService` | RSS/Atom parsing → update/new-software signals |
| `WebsiteCrawler` | Polite official-site crawl: **respects robots.txt**, JSON-LD + meta parsing |
| `Ingest` | The heart: dedup, version detection, scoring, publish decision, SEO |
| `DuplicateDetector` | External-ref / host / developer+name fuzzy matching with confidence |
| `TrustScore` | Trust (0–100) + quality scores from verified signals only |
| `Classifier` | Heuristic category + OS detection from text signals |
| `LinkChecker` | HEAD/GET verification; redirects are **not** errors; bounded batches |
| `Seo` | Factual titles/descriptions + `SoftwareApplication`/`BreadcrumbList` schema |
| `Sitemap` | Segmented sitemaps (software/categories/compare/alternatives) + index |
| `JobRunner` | Wraps every job: lock, timing, `crawler_logs`, status, failure notifications |

`JobRunner` guarantees **no automation task ever silently fails** — every run is
logged with processed/created/updated/skipped/failed counts, and repeated
failures raise admin notifications.

---

## Features implemented

**Public site** — modern responsive UI with light/dark mode:
- Homepage with 15 database-driven sections (popular, recently updated, new, free,
  open-source, per-OS, low-end PC, trending categories, latest updates, finder CTA)
- Software detail page (info cards, about, features, pros/cons, what's new, system
  requirements, screenshots, download info, alternatives, similar, older versions,
  "last checked" timestamp)
- Filterable listing (`/software`) — OS, category, license, price, open-source,
  max RAM, architecture, trust level, sort
- Category, subcategory and per-OS pages
- Full-text + `LIKE` **search** with natural-language hints and JSON autocomplete
- Interactive **Software Finder** with transparent scoring
- **Comparison** engine (`/compare/a-vs-b`, 2–4 products, indexable only when meaningful)
- **Alternatives** pages (curated + category fallback, noindex when thin)
- `/new-software`, `/software-updates` (today/yesterday/week/month), `/low-end-pc`
- Official **download redirect** (`/download/{slug}`) with outbound click tracking —
  clearly labelled destination, never pretends to self-host
- Clean SEO URLs, canonical tags, Open Graph, schema.org, `/sitemap.xml`,
  segmented sitemaps, dynamic `/robots.txt`

**Admin panel** (`/admin`):
- Dashboard (stat cards, growth chart, automation status, top viewed/downloaded, notifications)
- Software management (search/filter, edit, approve/reject/disable/delete, recheck, rebuild SEO)
- **Source Manager** (GitHub/Winget/RSS/Atom/website/manual, encrypted API keys, run-now)
- **Automation Center** (per-job status, last run, duration, counts, run/pause/enable, run logs)
- **Review queue** (pending software + duplicate candidates)
- **Settings** (branding, colors, thresholds, ad slots) — all configurable
- Role-based permissions (super_admin, editor, reviewer, seo_manager, automation_manager)

**Automation / cron** — `discover`, `github-sync`, `winget-sync`, `rss-sync`,
`version-check`, `link-check`, `seo-update`, `sitemap`, `cleanup`, `backup`,
each lock-guarded against overlap.

**GitHub webhook** (`POST /webhooks/github`) — HMAC-SHA256 signature validated
before any processing; on a new release it detects the version, updates the page,
changelog and sitemap, and logs the event.

---

## Security

- **PDO prepared statements** everywhere (`app/Core/Database.php`)
- **CSRF** tokens on every state-changing form (`app/Core/Csrf.php`)
- **Output escaping** via `e()` in all templates; XSS-safe by default
- **Password hashing** with `password_hash()` (bcrypt/argon)
- **Secure sessions** (HttpOnly, SameSite=Lax, Secure when HTTPS)
- **Login rate limiting** (5 failures / 15 min per IP) + audit logging
- **API keys encrypted at rest** with AES-256-GCM (`app/Core/Crypto.php`)
- **Webhook signature validation** (constant-time compare)
- **Security headers** incl. a conservative CSP (`app/Core/Response.php`)
- **Admin roles & permissions** enforced per action
- **Audit log** of admin actions
- Outbound redirects restricted to `http(s)` and validated URLs

API keys are never exposed to frontend JavaScript.

---

## Trust, quality & publishing rules

Trust score (0–100) is computed from **verified signals only**: official source
(+30), verified GitHub repo (+20), official download URL (+20), valid HTTPS (+10),
confirmed version (+10), recent verification (+10); unknown third-party sources are
penalised. Displayed as 🟢 Highly Verified / 🟡 Needs Review / 🔴 Unverified.

Auto-publish thresholds (configurable in **Settings**):

| Trust score | Outcome |
|---|---|
| ≥ 90 | Auto-publish |
| 70–89 | Auto-publish if all mandatory fields pass |
| 40–69 | Review required |
| < 40 | Rejected |

Mandatory fields: name, developer, version, supported OS, category, valid source URL.

---

## Configuring discovery sources

Add sources in **Admin → Source Manager**. The `config` field is JSON:

- **GitHub API** — `{"repos":["videolan/vlc","obsproject/obs-studio"],"min_stars":100}`
  (set `GITHUB_TOKEN` in `.env` or an encrypted per-source API key for higher rate limits)
- **Winget** — `{"packages":["Mozilla.Firefox","VideoLAN.VLC"]}`
- **RSS/Atom** — `{"category_id":12,"developer":"Vendor","price_type":"free"}`
- **Website** — `{"name":"App","developer":"Vendor","download_url":"https://…","category_id":1}`

The seed ships two example sources (GitHub + Winget) **paused** — enable and adjust
them in the admin, then use **Run now** or wait for cron.

---

## Notes & roadmap

This is a substantial, runnable foundation covering the full architecture of the
brief. Areas intentionally left as extension points (the plumbing exists; deeper
UIs/heuristics can grow over time): visual merge tool for duplicates, richer
per-source authentication for private/package repos, WebP/AVIF image pipeline,
and category-detection tuning. Everything is structured so these slot in without
rework.

## License

MIT — see `composer.json`. All product names and trademarks referenced in seed
data belong to their respective owners.
