<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentComplianceValidationService
{
    public function validate(array $sections, array $blueprint, array $resolvedRules): array
    {
        $items = [];

        $normalizedSections = [];
        foreach ($sections as $section) {
            $title = trim(mb_strtolower((string) ($section['title'] ?? '')));
            if ($title !== '') {
                $normalizedSections[] = $title;
            }
        }

        $expectedTitles = [];
        foreach ($blueprint as $section) {
            $title = trim(mb_strtolower((string) ($section['title'] ?? '')));
            if ($title !== '') {
                $expectedTitles[] = $title;
            }
        }

        if (count($sections) < 3) {
            $items[] = ['severity' => 'critical', 'rule' => 'minimum_structure', 'message' => 'Estrutura mínima inválida: o documento deve conter pelo menos 3 secções.'];
        }

        foreach ($expectedTitles as $index => $expected) {
            $actual = $normalizedSections[$index] ?? null;
            if ($actual === null) {
                $items[] = ['severity' => 'critical', 'rule' => 'required_section_missing', 'message' => sprintf('Secção obrigatória ausente na ordem esperada: "%s".', $expected)];
                continue;
            }
            if ($actual !== $expected) {
                $items[] = ['severity' => 'major', 'rule' => 'section_order', 'message' => sprintf('Ordem de secções divergente na posição %d. Esperado "%s" e encontrado "%s".', $index + 1, $expected, $actual)];
            }
        }

        $referenceStyle = trim((string) ($resolvedRules['referenceRules']['style'] ?? ''));
        $hasReferences = false;
        foreach ($normalizedSections as $title) {
            if (str_contains($title, 'refer')) {
                $hasReferences = true;
                break;
            }
        }
        if ($referenceStyle !== '' && !$hasReferences) {
            $items[] = ['severity' => 'critical', 'rule' => 'reference_rules', 'message' => sprintf('Secção de referências obrigatória não encontrada para o estilo %s.', $referenceStyle)];
        }

        $mandatoryElements = ['introdu', 'metodolog', 'conclus'];
        foreach ($mandatoryElements as $element) {
            $found = false;
            foreach ($normalizedSections as $title) {
                if (str_contains($title, $element)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $items[] = ['severity' => 'critical', 'rule' => 'mandatory_elements', 'message' => sprintf('Elemento obrigatório ausente: %s.', $element)];
            }
        }

        $summary = ['critical' => 0, 'major' => 0, 'minor' => 0];
        foreach ($items as $item) {
            $severity = (string) ($item['severity'] ?? 'minor');
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        return [
            'is_compliant' => $summary['critical'] === 0,
            'summary' => $summary,
            'non_conformities' => $items,
        ];
    }
}
