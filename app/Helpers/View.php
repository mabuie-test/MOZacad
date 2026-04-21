<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../../views/' . $template . '.php';
    }
}
