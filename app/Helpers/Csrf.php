<?php

declare(strict_types=1);

namespace App\Helpers;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $expected = (string) ($_SESSION[self::SESSION_KEY] ?? '');
        $provided = trim((string) $token);

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }
}
