<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Admin authentication + role-based permissions + login rate limiting.
 */
final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MIN = 15;

    /** Role => permitted capabilities. '*' means all. */
    private const PERMISSIONS = [
        'super_admin'        => ['*'],
        'editor'             => ['software.manage', 'category.manage', 'source.view'],
        'reviewer'           => ['software.review', 'duplicate.review'],
        'seo_manager'        => ['seo.manage', 'sitemap.manage', 'software.view'],
        'automation_manager' => ['automation.manage', 'source.manage', 'cron.run'],
    ];

    public static function attempt(string $email, string $password, string $ip): bool
    {
        if (self::tooManyAttempts($ip)) {
            return false;
        }

        $admin = Database::first('SELECT * FROM admins WHERE email = :e AND status = "active"', ['e' => $email]);
        $ok = $admin !== null && password_verify($password, $admin['password_hash']);

        Database::insert('login_attempts', ['ip' => $ip, 'email' => $email, 'success' => $ok ? 1 : 0]);

        if ($ok) {
            Session::start();
            Session::regenerate();
            Session::set('admin_id', (int) $admin['id']);
            Session::set('admin_role', $admin['role']);
            Session::set('admin_name', $admin['name']);
            Database::update('admins', ['last_login_at' => gmdate('Y-m-d H:i:s'), 'failed_logins' => 0], ['id' => $admin['id']]);
            return true;
        }

        if ($admin !== null) {
            Database::run('UPDATE admins SET failed_logins = failed_logins + 1 WHERE id = :id', ['id' => $admin['id']]);
        }
        return false;
    }

    public static function tooManyAttempts(string $ip): bool
    {
        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip = :ip AND success = 0 AND created_at > (NOW() - INTERVAL :w MINUTE)',
            ['ip' => $ip, 'w' => self::WINDOW_MIN]
        );
        return $count >= self::MAX_ATTEMPTS;
    }

    public static function check(): bool
    {
        Session::start();
        return Session::get('admin_id') !== null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id'   => Session::get('admin_id'),
            'name' => Session::get('admin_name'),
            'role' => Session::get('admin_role'),
        ];
    }

    public static function can(string $permission): bool
    {
        $role = Session::get('admin_role');
        if ($role === null) {
            return false;
        }
        $perms = self::PERMISSIONS[$role] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Response::redirect(base_url('/admin/login'));
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            Response::html('Forbidden: insufficient permissions.', 403);
        }
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
