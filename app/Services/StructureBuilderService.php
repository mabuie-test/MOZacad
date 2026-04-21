<?php

declare(strict_types=1);

namespace App\Services;

final class StructureBuilderService
{
    public function build(array $sections): array
    {
        usort($sections, static fn (array $a, array $b): int => ($a['section_order'] ?? 0) <=> ($b['section_order'] ?? 0));

        $blueprint = array_map(static fn (array $section): array => [
            'code' => (string) ($section['section_code'] ?? ''),
            'title' => (string) ($section['section_title'] ?? 'Secção'),
            'is_required' => (bool) ($section['is_required'] ?? true),
            'min_words' => (int) ($section['min_words'] ?? 250),
            'max_words' => (int) ($section['max_words'] ?? 900),
        ], $sections);

        if ($blueprint === []) {
            return [
                ['code' => 'resumo', 'title' => 'Resumo', 'is_required' => true, 'min_words' => 200, 'max_words' => 350],
                ['code' => 'introducao', 'title' => 'Introdução', 'is_required' => true, 'min_words' => 500, 'max_words' => 1000],
                ['code' => 'metodologia', 'title' => 'Metodologia', 'is_required' => true, 'min_words' => 600, 'max_words' => 1200],
                ['code' => 'resultados', 'title' => 'Resultados e Discussão', 'is_required' => true, 'min_words' => 900, 'max_words' => 1600],
                ['code' => 'conclusao', 'title' => 'Conclusão', 'is_required' => true, 'min_words' => 400, 'max_words' => 700],
            ];
        }

        return $blueprint;
    }
}
