<?php

declare(strict_types=1);

namespace App\Helpers;

use Dotenv\Dotenv;

final class Env
{
    private static bool $loaded = false;

    public static function boot(string $path): void
    {
        if (self::$loaded || !file_exists($path)) {
            return;
        }

        $dotenv = Dotenv::createImmutable(dirname($path), basename($path));
        $dotenv->safeLoad();
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}
