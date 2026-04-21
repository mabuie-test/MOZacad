<?php

declare(strict_types=1);

namespace App\Services;

final class CitationFormatterService
{
    public function format(array $sections, string $style = 'APA'): array
    {
        return [...$sections, "\nReferências formatadas em {$style}."];
    }
}
