<?php

declare(strict_types=1);

namespace App\Services;

final class StructureBuilderService
{
    public function build(array $sections, array $structureRules = []): array
    {
        $customStructure = $structureRules['custom_structure'] ?? [];
        if (is_array($customStructure) && $customStructure !== []) {
            return $this->normalizeSections($customStructure);
        }

        $levelId = (int) ($structureRules['academic_level_id'] ?? 0);
        $filtered = array_filter($sections, static function (array $section) use ($levelId): bool {
            $appliesToLevel = (int) ($section['applies_to_level'] ?? 0);
            return $appliesToLevel === 0 || $levelId === 0 || $appliesToLevel === $levelId;
        });

        $normalized = $this->normalizeSections($filtered);
        if ($normalized !== []) {
            return $normalized;
        }

        return [
            ['code' => 'resumo', 'title' => 'Resumo', 'is_required' => true, 'min_words' => 200, 'max_words' => 350],
            ['code' => 'introducao', 'title' => 'Introdução', 'is_required' => true, 'min_words' => 500, 'max_words' => 1000],
            ['code' => 'metodologia', 'title' => 'Metodologia', 'is_required' => true, 'min_words' => 600, 'max_words' => 1200],
            ['code' => 'resultados', 'title' => 'Resultados e Discussão', 'is_required' => true, 'min_words' => 900, 'max_words' => 1600],
            ['code' => 'conclusao', 'title' => 'Conclusão', 'is_required' => true, 'min_words' => 400, 'max_words' => 700],
        ];
    }

    private function normalizeSections(array $sections): array
    {
        usort($sections, static fn (array $a, array $b): int => ((int) ($a['section_order'] ?? 0)) <=> ((int) ($b['section_order'] ?? 0)));

        return array_values(array_map(static fn (array $section): array => [
            'code' => (string) ($section['section_code'] ?? $section['code'] ?? ''),
            'title' => (string) ($section['section_title'] ?? $section['title'] ?? 'Secção'),
            'is_required' => (bool) ($section['is_required'] ?? true),
            'min_words' => max(50, (int) ($section['min_words'] ?? 250)),
            'max_words' => max(100, (int) ($section['max_words'] ?? 900)),
        ], $sections));
    }
}
