<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;

final class Config
{
    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    public static function get(string $name): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $path = dirname(__DIR__, 2) . '/config/' . $name . '.php';
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Arquivo de configuração não encontrado: config/%s.php', $name));
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException(sprintf('Configuração inválida em config/%s.php', $name));
        }

        self::$cache[$name] = $config;

        return $config;
    }
}
