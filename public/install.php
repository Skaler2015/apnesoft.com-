<?php

declare(strict_types=1);

/**
 * SoftwareHub web installer (for shared hosting like Hostinger).
 *
 * Visit this file in your browser once after uploading the files:
 *   https://your-domain/install.php
 *
 * It will: test the database connection, write your .env file, create all
 * tables, load seed data, create your admin account, and build the sitemaps.
 *
 * SECURITY: it refuses to run once installed (a lock file is written), and you
 * MUST delete this file afterwards. There is a self-delete button on success.
 */

$bootstrap = null;
foreach ([
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../softwarehub/app/bootstrap.php',
    getenv('SOFTWAREHUB_BASE') ? rtrim((string) getenv('SOFTWAREHUB_BASE'), '/') . '/app/bootstrap.php' : null,
] as $c) {
    if ($c && is_file($c)) { $bootstrap = $c; break; }
}
if ($bootstrap === null) {
    exit('Application core (app/bootstrap.php) not found next to this file.');
}
require $bootstrap;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

Session::start();
if (!Session::get('_install_token')) {
    Session::set('_install_token', bin2hex(random_bytes(16)));
}
$token = Session::get('_install_token');

$root       = (string) Config::get('paths.root');
$storageDir = (string) Config::get('paths.storage');
$lockFile   = $storageDir . '/installed.lock';
$envFile    = $root . '/.env';
$publicDir  = __DIR__;

$errors = [];
$done = false;

// ---------------------------------------------------------------------------
// Handle self-delete request (after successful install).
// ---------------------------------------------------------------------------
if (($_POST['_action'] ?? '') === 'self_delete' && hash_equals($token, $_POST['_token'] ?? '')) {
    @unlink(__FILE__);
    header('Location: ' . rtrim((string) detectUrl(), '/') . '/admin/login');
    exit;
}

$alreadyInstalled = is_file($lockFile);

// ---------------------------------------------------------------------------
// Handle install submission.
// ---------------------------------------------------------------------------
if (($_POST['_action'] ?? '') === 'install' && !$alreadyInstalled) {
    if (!hash_equals($token, $_POST['_token'] ?? '')) {
        $errors[] = 'Security token mismatch. Reload the page and try again.';
    }

    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $dbPort = (int) ($_POST['db_port'] ?? 3306);

    $adminName  = trim($_POST['admin_name'] ?? 'Administrator');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = (string) ($_POST['admin_pass'] ?? '');
    $appUrl     = rtrim(trim($_POST['app_url'] ?? detectUrl()), '/');

    if ($dbName === '' || $dbUser === '') {
        $errors[] = 'Database name and user are required.';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin email is required.';
    }
    if (strlen($adminPass) < 10) {
        $errors[] = 'Admin password must be at least 10 characters.';
    }

    // Point the app config at the submitted DB before touching the database.
    if (!$errors) {
        $all = Config::all();
        $all['db'] = ['host' => $dbHost, 'port' => $dbPort, 'name' => $dbName, 'user' => $dbUser, 'pass' => $dbPass, 'charset' => 'utf8mb4'];
        $all['app']['url'] = $appUrl;
        Config::load($all);

        try {
            $pdo = Database::connection();
        } catch (\Throwable $e) {
            $errors[] = 'Could not connect to the database: ' . $e->getMessage()
                . ' — check the host, database name, user and password.';
        }
    }

    if (!$errors) {
        try {
            // 1. Write .env so future requests are configured.
            $appKey = bin2hex(random_bytes(32));
            $env = envTemplate($appUrl, $appKey, $dbHost, $dbPort, $dbName, $dbUser, $dbPass, $publicDir);
            if (@file_put_contents($envFile, $env) === false) {
                $errors[] = 'Could not write .env at ' . $envFile . ' — check folder permissions (or create it manually).';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Failed writing .env: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            // 2. Schema + seed.
            runSqlFile($pdo, $root . '/database/schema.sql');
            runSqlFile($pdo, $root . '/database/seed.sql');

            // 3. Admin account.
            Database::run(
                'INSERT INTO admins (name, email, password_hash, role, status)
                 VALUES (:n, :e, :p, "super_admin", "active")
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name), status = "active"',
                ['n' => $adminName, 'e' => $adminEmail, 'p' => Auth::hash($adminPass)]
            );

            // 4. SEO + sitemaps.
            foreach (Database::all('SELECT id FROM software WHERE status = "published"') as $r) {
                \App\Services\Seo::generateForSoftware((int) $r['id']);
            }
            \App\Services\Sitemap::generateAll();

            // 5. Lock.
            @mkdir($storageDir, 0775, true);
            @file_put_contents($lockFile, gmdate('c'));
            $done = true;
        } catch (\Throwable $e) {
            $errors[] = 'Installation error: ' . $e->getMessage();
        }
    }
}

// Prefill values from existing config where possible.
$cfg = Config::get('db');
$prefill = [
    'db_host'    => $cfg['host'] ?: 'localhost',
    'db_port'    => $cfg['port'] ?: 3306,
    'db_name'    => $_POST['db_name'] ?? ($cfg['name'] !== 'softwarehub' ? $cfg['name'] : ''),
    'db_user'    => $_POST['db_user'] ?? ($cfg['user'] !== 'root' ? $cfg['user'] : ''),
    'app_url'    => $_POST['app_url'] ?? detectUrl(),
    'admin_name' => $_POST['admin_name'] ?? 'Administrator',
    'admin_email'=> $_POST['admin_email'] ?? '',
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function detectUrl(): string
{
    $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function envTemplate(string $url, string $key, string $h, int $port, string $name, string $user, string $pass, string $publicDir): string
{
    $q = static fn($v) => '"' . str_replace('"', '\"', $v) . '"';
    return <<<ENV
APP_NAME="SoftwareHub"
APP_ENV=production
APP_DEBUG=false
APP_URL={$q($url)}
APP_KEY={$key}
APP_PUBLIC_DIR={$q($publicDir)}

DB_HOST={$q($h)}
DB_PORT={$port}
DB_NAME={$q($name)}
DB_USER={$q($user)}
DB_PASS={$q($pass)}

GITHUB_TOKEN=
GITHUB_WEBHOOK_SECRET=

CRON_ENABLED=true
HTTP_USER_AGENT="SoftwareHubBot/1.0 (+{$url}/bot)"

ENV;
}

function runSqlFile(\PDO $pdo, string $file): void
{
    if (!is_file($file)) {
        throw new \RuntimeException('Missing SQL file: ' . basename($file));
    }
    $sql = (string) file_get_contents($file);
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (\PDOException $e) {
            if (!str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }
        }
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Install SoftwareHub</title>
<style>
 body{font-family:system-ui,Segoe UI,Roboto,sans-serif;background:#f4f5fa;color:#1a1d29;margin:0;padding:40px 16px}
 .box{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e4e7ee;border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(20,25,45,.08)}
 h1{margin:0 0 4px;font-size:1.5rem}.sub{color:#697086;margin:0 0 20px}
 label{display:block;font-size:.85rem;font-weight:600;margin:12px 0 4px}
 input{width:100%;padding:.6em;border:1px solid #d7dbe6;border-radius:8px;font-size:.95rem}
 .row{display:flex;gap:12px}.row>div{flex:1}
 .btn{margin-top:20px;width:100%;background:#4f46e5;color:#fff;border:none;padding:.8em;border-radius:9px;font-weight:700;font-size:1rem;cursor:pointer}
 .btn.gray{background:#697086}
 fieldset{border:1px solid #eef0f5;border-radius:10px;padding:12px 16px;margin:16px 0}
 legend{font-weight:700;font-size:.9rem;padding:0 6px}
 .err{background:#fde8e8;color:#b91c1c;border-radius:8px;padding:12px;margin-bottom:16px;font-size:.9rem}
 .ok{background:#e7f6ec;color:#15803d;border-radius:8px;padding:14px;margin-bottom:16px}
 .note{font-size:.8rem;color:#697086;margin-top:6px}
 code{background:#f1f3f8;padding:.1em .4em;border-radius:4px}
 a{color:#4f46e5}
</style>
</head>
<body>
<div class="box">
<h1>◆ Install SoftwareHub</h1>
<p class="sub">apnesoft.com — one-time setup</p>

<?php if ($alreadyInstalled && !$done): ?>
    <div class="ok"><strong>Already installed.</strong> A lock file exists at <code>storage/installed.lock</code>.
    To re-run the installer, delete that file first. Otherwise, please delete <code>install.php</code> now.</div>
    <p><a href="<?= h(detectUrl()) ?>/admin/login">→ Go to admin login</a></p>

<?php elseif ($done): ?>
    <div class="ok"><strong>Installation complete! 🎉</strong><br>
    Database tables created, seed data loaded, sitemaps built, and your admin account is ready.</div>
    <p><strong>Important:</strong> delete this installer file now for security.</p>
    <form method="post">
        <input type="hidden" name="_action" value="self_delete">
        <input type="hidden" name="_token" value="<?= h($token) ?>">
        <button class="btn" type="submit">Delete installer &amp; go to admin login</button>
    </form>
    <p class="note">If the delete fails (permissions), remove <code>public_html/install.php</code> manually via File Manager.</p>

<?php else: ?>
    <?php foreach ($errors as $e): ?><div class="err"><?= h($e) ?></div><?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="_action" value="install">
        <input type="hidden" name="_token" value="<?= h($token) ?>">

        <label>Site URL</label>
        <input name="app_url" value="<?= h((string) $prefill['app_url']) ?>" required>

        <fieldset>
            <legend>Database</legend>
            <p class="note">Create a MySQL database + user in hPanel → Databases first, then paste the details here. On Hostinger the host is usually <code>localhost</code> and names look like <code>u246829578_softwarehub</code>.</p>
            <div class="row">
                <div><label>DB host</label><input name="db_host" value="<?= h((string) $prefill['db_host']) ?>" required></div>
                <div><label>DB port</label><input name="db_port" value="<?= h((string) $prefill['db_port']) ?>"></div>
            </div>
            <label>DB name</label><input name="db_name" value="<?= h((string) $prefill['db_name']) ?>" required>
            <label>DB user</label><input name="db_user" value="<?= h((string) $prefill['db_user']) ?>" required>
            <label>DB password</label><input name="db_pass" type="password" autocomplete="off">
        </fieldset>

        <fieldset>
            <legend>Admin account</legend>
            <label>Name</label><input name="admin_name" value="<?= h((string) $prefill['admin_name']) ?>">
            <label>Email</label><input name="admin_email" type="email" value="<?= h((string) $prefill['admin_email']) ?>" required>
            <label>Password (min 10 chars)</label><input name="admin_pass" type="password" autocomplete="new-password" required>
        </fieldset>

        <button class="btn" type="submit">Install now</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
