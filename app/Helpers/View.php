<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $safeData = self::sanitizeKeys($data);
        extract($safeData, EXTR_SKIP);
        $viewData = $safeData;

        require __DIR__ . '/../../views/' . $template . '.php';
    }

    private static function sanitizeKeys(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            if (in_array($key, ['GLOBALS', 'this', 'template', 'viewData'], true)) {
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }
}
