<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OrderBriefingDTO;

final class RequirementInterpreterService
{
    public function interpret(array $order, array $requirements): OrderBriefingDTO
    {
        $specificObjectives = json_decode((string) ($order['specific_objectives_json'] ?? '[]'), true);
        $keywords = json_decode((string) ($order['keywords_json'] ?? '[]'), true);

        return new OrderBriefingDTO(
            orderId: (int) $order['id'],
            title: trim((string) ($order['title_or_theme'] ?? 'Tema académico')),
            problem: $order['problem_statement'] ? trim((string) $order['problem_statement']) : null,
            generalObjective: $order['general_objective'] ? trim((string) $order['general_objective']) : null,
            specificObjectives: is_array($specificObjectives) ? array_values(array_filter(array_map('strval', $specificObjectives))) : [],
            keywords: is_array($keywords) ? array_values(array_filter(array_map('strval', $keywords))) : [],
            extras: [
                'needs_cover' => (bool) ($requirements['needs_institution_cover'] ?? false),
                'needs_abstract' => (bool) ($requirements['needs_abstract'] ?? true),
                'needs_bilingual_abstract' => (bool) ($requirements['needs_bilingual_abstract'] ?? false),
                'needs_methodology_review' => (bool) ($requirements['needs_methodology_review'] ?? false),
                'needs_humanized_revision' => (bool) ($requirements['needs_humanized_revision'] ?? false),
                'needs_slides' => (bool) ($requirements['needs_slides'] ?? false),
                'needs_defense_summary' => (bool) ($requirements['needs_defense_summary'] ?? false),
                'target_pages' => (int) ($order['target_pages'] ?? 0),
                'complexity_level' => (string) ($order['complexity_level'] ?? 'medium'),
                'deadline_date' => (string) ($order['deadline_date'] ?? ''),
                'notes' => $requirements['notes'] ?? $order['notes'] ?? null,
            ],
            raw: ['order' => $order, 'requirements' => $requirements],
        );
    }
}
