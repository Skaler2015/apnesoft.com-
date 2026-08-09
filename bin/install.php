<?php

declare(strict_types=1);

/**
 * SoftwareHub installer / migrator (CLI only).
 *
 *   php bin/install.php               # run schema + seed, register jobs
 *   php bin/install.php --admin       # additionally create/update an admin
 *   php bin/install.php --fresh       # DROP existing tables first (destructive)
 *
 * Requires a configured .env (copy from .env.example).
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Services\Seo;
use App\Services\Sitemap;

$args = $argv;
$fresh = in_array('--fresh', $args, true);
$withAdmin = in_array('--admin', $args, true);

echo "SoftwareHub installer\n=====================\n";

// Verify DB connectivity.
try {
    Database::connection();
    echo "✓ Connected to database '" . Config::get('db.name') . "'\n";
} catch (\Throwable $e) {
    exit("✗ Cannot connect to database: " . $e->getMessage() . "\n  Check your .env settings.\n");
}

$pdo = Database::connection();

if ($fresh) {
    echo "Dropping existing tables (--fresh)...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Run schema.
echo "Applying schema...\n";
run_sql_file($pdo, dirname(__DIR__) . '/database/schema.sql');
echo "✓ Schema applied\n";

// Run seed.
echo "Seeding reference data...\n";
run_sql_file($pdo, dirname(__DIR__) . '/database/seed.sql');
echo "✓ Seed data loaded\n";

// Generate SEO for published software.
$rows = Database::all('SELECT id FROM software WHERE status = "published"');
foreach ($rows as $r) {
    Seo::generateForSoftware((int) $r['id']);
}
echo "✓ Generated SEO metadata for " . count($rows) . " items\n";

// Build sitemaps.
$counts = Sitemap::generateAll();
echo "✓ Sitemaps built (" . array_sum($counts) . " urls)\n";

// Create admin.
if ($withAdmin) {
    $email = readline_prompt('Admin email: ');
    $name = readline_prompt('Admin name: ') ?: 'Administrator';
    $password = readline_prompt('Admin password (min 10 chars): ', true);
    if (strlen($password) < 10) {
        exit("✗ Password too short.\n");
    }
    Database::run(
        'INSERT INTO admins (name, email, password_hash, role, status)
         VALUES (:n, :e, :p, "super_admin", "active")
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name), status = "active"',
        ['n' => $name, 'e' => $email, 'p' => Auth::hash($password)]
    );
    echo "✓ Super admin ready: $email\n";
} else {
    $count = (int) Database::scalar('SELECT COUNT(*) FROM admins');
    if ($count === 0) {
        echo "\nℹ No admin exists yet. Create one with:\n    php bin/install.php --admin\n";
    }
}

echo "\nDone. Point your web root at /public and visit /admin/login.\n";

// --- helpers ----------------------------------------------------------------
function run_sql_file(\PDO $pdo, string $file): void
{
    if (!is_file($file)) {
        exit("✗ Missing SQL file: $file\n");
    }
    $sql = file_get_contents($file);
    // Split on semicolons at line ends, keeping it simple (schema uses standard DDL).
    $statements = preg_split('/;\s*\n/', $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (\PDOException $e) {
            // Ignore benign "table exists" errors; surface others.
            if (!str_contains($e->getMessage(), 'already exists')) {
                fwrite(STDERR, "  SQL warning: " . $e->getMessage() . "\n");
            }
        }
    }
}

function readline_prompt(string $label, bool $hidden = false): string
{
    echo $label;
    if ($hidden && stripos(PHP_OS, 'WIN') === false) {
        @shell_exec('stty -echo');
        $line = rtrim((string) fgets(STDIN), "\n");
        @shell_exec('stty echo');
        echo "\n";
        return $line;
    }
    return rtrim((string) fgets(STDIN), "\n");
}
