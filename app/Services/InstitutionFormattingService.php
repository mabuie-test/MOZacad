<?php

declare(strict_types=1);

namespace App\Services;

final class InstitutionFormattingService
{
    public function apply(array $sections, array $rules): array
    {
        $appliedRules = [
            'font_family' => $rules['visualRules']['font_family'] ?? 'Times New Roman',
            'font_size' => (float) ($rules['visualRules']['font_size'] ?? 12),
            'heading_font_size' => (float) ($rules['visualRules']['heading_font_size'] ?? 14),
            'line_spacing' => (float) ($rules['visualRules']['line_spacing'] ?? 1.5),
            'margins' => [
                'top' => (float) ($rules['visualRules']['margin_top'] ?? 2.5),
                'right' => (float) ($rules['visualRules']['margin_right'] ?? 3),
                'bottom' => (float) ($rules['visualRules']['margin_bottom'] ?? 2.5),
                'left' => (float) ($rules['visualRules']['margin_left'] ?? 3),
            ],
            'references_style' => $rules['referenceRules']['style'] ?? 'APA',
            'front_page' => $rules['visualRules']['front_page'] ?? [],
        ];

        $formattedSections = array_map(static function (array $section): array {
            $content = trim((string) ($section['content'] ?? ''));
            return $section + [
                'word_count' => str_word_count($content),
                'format_profile' => 'institutional',
            ];
        }, $sections);

        return [
            'rules' => $appliedRules,
            'sections' => $formattedSections,
            'meta' => [
                'formatted_at' => date('c'),
                'section_count' => count($formattedSections),
            ],
        ];
    }
}
