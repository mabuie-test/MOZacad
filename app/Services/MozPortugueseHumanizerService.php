<?php

declare(strict_types=1);

namespace App\Services;

final class MozPortugueseHumanizerService
{
    public function humanize(array $sections, string $profile = 'academic_humanized'): array
    {
        return array_map(fn(string $text) => str_replace('você', 'o estudante', $text) . "\nPerfil: {$profile}", $sections);
    }
}
