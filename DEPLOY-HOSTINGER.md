# Deploying SoftwareHub on Hostinger (apnesoft.com)

This guide is written for the **Cloud Startup** plan shown in hPanel. It uses the
included **web installer** so you don't need SSH — everything is done from the
browser and File Manager. (An SSH path is at the bottom if you prefer it.)

The secure layout we'll create:

```
/home/u246829578/                 (your home directory)
├── softwarehub/                  ← app code (NOT web-accessible)  = repo minus /public
│   ├── app/  config/  cron/  database/  storage/  bin/  .env
└── public_html/                  ← web root (what visitors reach) = contents of repo /public
    ├── index.php  install.php  .htaccess  assets/  sitemaps/
```

Putting the app code **outside** `public_html` means your database password and
source code can never be downloaded over the web.

---

## Step 1 — Create the MySQL database

hPanel → **Databases** → *MySQL Databases* → **Create**.

Note down the four values it gives you:
- Database name — e.g. `u246829578_softwarehub`
- Database user — e.g. `u246829578_admin`
- Password — the one you set
- Host — usually `localhost`

## Step 2 — Get the files

Download the repository as a ZIP from GitHub (branch
`claude/software-discovery-platform-9f8r10`) → **Code → Download ZIP**, and unzip
it on your computer. You'll see folders `app/`, `public/`, `config/`, `cron/`, etc.

## Step 3 — Upload

Using **File Manager** (or FileZilla with the FTP details from *Files → FTP Accounts*):

1. In your **home directory** (the one containing `public_html`), create a new
   folder named **`softwarehub`**.
2. Upload everything **except the `public/` folder** into `softwarehub/`
   → so you get `softwarehub/app`, `softwarehub/config`, `softwarehub/cron`,
   `softwarehub/database`, `softwarehub/storage`, `softwarehub/bin`.
3. Upload the **contents of the `public/` folder** into **`public_html/`**
   → so you get `public_html/index.php`, `public_html/install.php`,
   `public_html/.htaccess`, `public_html/assets/`.

> The files already know how to find each other: `public_html/index.php`
> automatically looks for the app in `../softwarehub/app`.

Make sure the `softwarehub/storage` folder is **writable** (File Manager →
right-click → Permissions → 755 or 775, apply to subfolders).

## Step 4 — Run the web installer

Open **https://apnesoft.com/install.php** in your browser and fill in:

- **Site URL** — `https://apnesoft.com` (pre-filled)
- **Database** — the name/user/password/host from Step 1
- **Admin account** — your name, email and a password (min 10 characters)

Click **Install now**. The installer will:
1. test the database connection,
2. write your `.env` file (with a fresh secret key),
3. create all tables and load seed data (categories, sample apps),
4. create your admin login,
5. build the sitemaps.

On success, click **Delete installer** — this removes `install.php` for security.
(If it can't self-delete, delete `public_html/install.php` manually.)

## Step 5 — Log in

Go to **https://apnesoft.com/admin/login** and sign in with the admin account you
just created. You'll land on the dashboard.

## Step 6 — Turn on automation (cron jobs)

hPanel → **Advanced → Cron Jobs**. Add these (adjust the PHP path if hPanel shows
a different one — it usually pre-fills `/usr/bin/php`). Use the **full path**
`/home/u246829578/softwarehub/cron/...`:

| Schedule | Command |
|---|---|
| Every 6 hours | `/usr/bin/php /home/u246829578/softwarehub/cron/discover.php` |
| Every 3 hours | `/usr/bin/php /home/u246829578/softwarehub/cron/rss-sync.php` |
| Once a day | `/usr/bin/php /home/u246829578/softwarehub/cron/link-check.php` |
| Once a day | `/usr/bin/php /home/u246829578/softwarehub/cron/seo-update.php` |
| Once a day | `/usr/bin/php /home/u246829578/softwarehub/cron/sitemap.php` |
| Once a day | `/usr/bin/php /home/u246829578/softwarehub/cron/backup.php` |

(See `deploy/crontab.example` for the full list.)

## Step 7 — Add discovery sources

In the admin: **Source Manager → Add source**. Two examples ship *paused* — open
them, set **status = active**, and hit **Run now** to pull real data:
- **GitHub — Popular OSS** (`{"repos":["videolan/vlc","obsproject/obs-studio"],"min_stars":100}`)
- **Winget — Common Apps** (`{"packages":["Mozilla.Firefox","VideoLAN.VLC"]}`)

For higher GitHub rate limits, add a GitHub token: edit `softwarehub/.env` and set
`GITHUB_TOKEN=...`.

---

## Alternative: deploy with SSH (optional)

Cloud Startup includes SSH. If you'd rather use the command line:

```bash
ssh u246829578@82.180.164.152 -p 65002        # port shown in hPanel → Advanced → SSH
cd ~
git clone -b claude/software-discovery-platform-9f8r10 https://github.com/Skaler2015/apnesoft.com-.git softwarehub
# move the public contents into public_html:
cp -r softwarehub/public/. public_html/
cp softwarehub/.env.example softwarehub/.env
nano softwarehub/.env       # set APP_URL, DB_*, APP_PUBLIC_DIR=/home/u246829578/public_html
php softwarehub/bin/install.php --admin
```

Then do Steps 6–7 above. Delete `public_html/install.php` if you uploaded it.

---

## Troubleshooting

- **"Application core not found"** on the homepage → the `softwarehub` folder isn't
  a sibling of `public_html`, or `public_html/index.php` is missing. Re-check Step 3.
- **500 error after install** → open `softwarehub/storage/logs/app.log`. Usually a
  DB credential typo in `.env`, or `storage/` not writable.
- **Blank sitemap** → run the `sitemap` cron once, or re-save any software in admin.
- **CSS not loading** → make sure `assets/` is inside `public_html/`, and `APP_URL`
  in `.env` matches `https://apnesoft.com` exactly (no trailing slash).
