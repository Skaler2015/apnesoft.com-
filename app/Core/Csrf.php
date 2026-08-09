<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function verify(?string $token): bool
    {
        Session::start();
        $stored = Session::get('_csrf');
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }

    /** Abort with 419 if the request token is invalid. */
    public static function check(Request $request): void
    {
        if (!self::verify($request->input('_csrf'))) {
            Response::html('Invalid or missing CSRF token.', 419);
        }
    }
}
