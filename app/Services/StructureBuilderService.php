<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class StructureBuilderService
{
    public function build(array $sections, array $structureRules = []): array
    {
        $customStructure = $structureRules['custom_structure'] ?? [];
        if (is_array($customStructure) && $customStructure !== []) {
            return $this->normalizeSections($customStructure);
        }

        $flags = $this->resolveStructureFlags($structureRules);
        $this->validateRuleCombination($flags);

        $levelId = (int) ($structureRules['academic_level_id'] ?? 0);
        $filtered = array_filter($sections, static function (array $section) use ($levelId): bool {
            $appliesToLevel = (int) ($section['applies_to_level'] ?? 0);
            return $appliesToLevel === 0 || $levelId === 0 || $appliesToLevel === $levelId;
        });

        $normalized = $this->normalizeSections($filtered);
        if ($normalized !== []) {
            return $normalized;
        }

        return $this->buildFallbackStructure($flags);
    }

    /**
     * @return array{
     *     requiresMethodology: bool,
     *     requiresResultsDiscussion: bool,
     *     isTheoretical: bool,
     *     isEmpirical: bool
     * }
     */
    private function resolveStructureFlags(array $structureRules): array
    {
        $isTheoretical = $this->toBool($structureRules['is_theoretical'] ?? false);
        $isEmpirical = $this->toBool($structureRules['is_empirical'] ?? false);

        return [
            'requiresMethodology' => $this->toBool($structureRules['requires_methodology'] ?? false),
            'requiresResultsDiscussion' => $this->toBool($structureRules['requires_results_discussion'] ?? false),
            'isTheoretical' => $isTheoretical,
            'isEmpirical' => $isEmpirical,
        ];
    }

    private function validateRuleCombination(array $flags): void
    {
        if ($flags['isTheoretical'] && $flags['isEmpirical']) {
            throw new InvalidArgumentException('As regras de estrutura não podem definir o trabalho como teórico e empírico simultaneamente.');
        }

        if (! $flags['isTheoretical'] && ! $flags['isEmpirical'] && ! $flags['requiresResultsDiscussion']) {
            throw new InvalidArgumentException('Combinação incoerente: falta secção analítica principal (teórica ou resultados/discussão).');
        }
    }

    private function buildFallbackStructure(array $flags): array
    {
        $structure = [
            ['code' => 'resumo', 'title' => 'Resumo', 'is_required' => true, 'min_words' => 200, 'max_words' => 350],
            ['code' => 'introducao', 'title' => 'Introdução', 'is_required' => true, 'min_words' => 500, 'max_words' => 1000],
            ['code' => 'conclusao', 'title' => 'Conclusão', 'is_required' => true, 'min_words' => 400, 'max_words' => 700],
        ];

        if ($flags['isEmpirical'] || $flags['requiresMethodology']) {
            array_splice($structure, 2, 0, [[
                'code' => 'metodologia',
                'title' => 'Metodologia',
                'is_required' => true,
                'min_words' => 600,
                'max_words' => 1200,
            ]]);
        }

        if ($flags['isTheoretical']) {
            array_splice($structure, count($structure) - 1, 0, [[
                'code' => 'analise_teorica',
                'title' => 'Revisão/Análise teórica',
                'is_required' => true,
                'min_words' => 900,
                'max_words' => 1600,
            ]]);
        } elseif ($flags['isEmpirical'] || $flags['requiresResultsDiscussion']) {
            array_splice($structure, count($structure) - 1, 0, [[
                'code' => 'resultados',
                'title' => 'Resultados e Discussão',
                'is_required' => true,
                'min_words' => 900,
                'max_words' => 1600,
            ]]);
        }

        return $structure;
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

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }
}
