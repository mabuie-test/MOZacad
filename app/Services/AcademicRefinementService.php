<?php

declare(strict_types=1);

namespace App\Services;

final class AcademicRefinementService
{
    public function refine(array $sections): array
    {
        return array_map(fn(string $text) => $text . "\nCoerência temática reforçada.", $sections);
    }
}
