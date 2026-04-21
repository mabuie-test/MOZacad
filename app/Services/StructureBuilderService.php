<?php

declare(strict_types=1);

namespace App\Services;

final class StructureBuilderService
{
    public function build(array $sections): array
    {
        usort($sections, fn ($a, $b) => ($a['section_order'] ?? 0) <=> ($b['section_order'] ?? 0));
        return array_map(fn ($section) => [
            'code' => $section['section_code'],
            'title' => $section['section_title'],
            'is_required' => (bool)$section['is_required'],
            'min_words' => (int)($section['min_words'] ?? 0),
            'max_words' => (int)($section['max_words'] ?? 0),
        ], $sections);
    }
}
