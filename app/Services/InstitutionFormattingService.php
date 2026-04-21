<?php

declare(strict_types=1);

namespace App\Services;

final class InstitutionFormattingService
{
    public function apply(array $sections, array $rules): array
    {
        return [
            'rules' => [
                'font_family' => $rules['visualRules']['font_family'] ?? 'Times New Roman',
                'font_size' => (float) ($rules['visualRules']['font_size'] ?? 12),
                'line_spacing' => (float) ($rules['visualRules']['line_spacing'] ?? 1.5),
                'references_style' => $rules['referenceRules']['style'] ?? 'APA',
                'front_page' => $rules['visualRules']['front_page'] ?? [],
            ],
            'sections' => $sections,
        ];
    }
}
