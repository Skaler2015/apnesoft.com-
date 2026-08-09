<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Authenticated symmetric encryption for at-rest secrets (e.g. source API keys).
 * Uses AES-256-GCM with a key derived from APP_KEY. Not for password storage.
 */
final class Crypto
{
    private static function key(): string
    {
        $appKey = (string) Config::get('app.key', '');
        return hash('sha256', 'sh-crypto|' . $appKey, true);
    }

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }
}
