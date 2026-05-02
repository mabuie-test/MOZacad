<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\UnicodeWordCounter;

final class InstitutionFormattingService
{
    public function apply(array $sections, array $rules): array
    {
        $frontPage = is_array($rules['visualRules']['front_page'] ?? null) ? $rules['visualRules']['front_page'] : [];
        $normalizedSections = $this->normalizeAcademicSections($sections);

        return [
            'rules' => [
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
                'front_page' => $frontPage,
                'institution_norm' => $rules['meta']['institution_norm'] ?? [],
                'norm_notes' => $rules['meta']['notes'] ?? [],
                'norm_profile' => $rules['meta']['institution_norm']['profile'] ?? [],
                'template_resolution' => $rules['meta']['template_resolution'] ?? ['mode' => 'programmatic_assembly'],
            ],
            'sections' => array_map(static function (array $section): array {
                $content = trim((string) ($section['content'] ?? ''));

                return $section + [
                    'word_count' => UnicodeWordCounter::count($content),
                    'format_profile' => 'institutional',
                    'requires_manual_review' => str_contains(mb_strtolower($content), 'revisão manual') || (bool) ($section['requires_manual_completion'] ?? false),
                ];
            }, $normalizedSections),
            'meta' => ['formatted_at' => date('c'), 'section_count' => count($sections)],
        ];
    }

    private function normalizeAcademicSections(array $sections): array
    {
        $priority = ['introducao', 'objectivos', 'metodologia', 'desenvolvimento', 'conclusao', 'references'];
        $ordered = [];
        $remaining = array_values($sections);

        foreach ($priority as $wanted) {
            foreach ($remaining as $idx => $section) {
                $key = $this->normalizeKey((string) ($section['code'] ?? $section['title'] ?? ''));
                if ($this->isEquivalentSection($wanted, $key)) {
                    $ordered[] = $section;
                    unset($remaining[$idx]);
                }
            }
        }

        foreach ($remaining as $section) {
            $ordered[] = $section;
        }

        return array_values($ordered);
    }

    private function isEquivalentSection(string $canonical, string $value): bool
    {
        return in_array($value, match ($canonical) {
            'introducao' => ['introducao', 'introduction'],
            'objectivos' => ['objectivos', 'objetivos', 'objective', 'objectives'],
            'metodologia' => ['metodologia', 'methodology'],
            'desenvolvimento' => ['desenvolvimento', 'fundamentacao', 'development'],
            'conclusao' => ['conclusao', 'consideracoes finais'],
            'references' => ['references', 'referencias', 'bibliografia'],
            default => [$canonical],
        }, true);
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
        $value = preg_replace('/[^a-z0-9\s_]/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
