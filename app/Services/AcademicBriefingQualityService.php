<?php

declare(strict_types=1);

namespace App\Services;

final class AcademicBriefingQualityService
{
    public function evaluate(array $briefing, int $minSpecific = 3): array
    {
        $issues = [];
        $problem = trim((string) ($briefing['problem_statement'] ?? $briefing['problem'] ?? ''));
        $general = trim((string) ($briefing['general_objective'] ?? $briefing['generalObjective'] ?? ''));
        $specific = array_values(array_filter(array_map(static fn ($i) => trim((string) $i), (array) ($briefing['specific_objectives'] ?? $briefing['specificObjectives'] ?? [])), static fn ($v) => $v !== ''));

        if ($problem === '' || !str_contains($problem, '?')) { $issues[] = 'problem_not_question'; }
        if ($general === '' || !$this->hasAcademicVerb($general)) { $issues[] = 'general_objective_weak'; }
        if (count($specific) < $minSpecific) { $issues[] = 'specific_objectives_insufficient'; }

        $seen = [];
        foreach ($specific as $item) {
            if (str_word_count($item) < 4) { $issues[] = 'specific_objective_too_short'; }
            if (!$this->hasAcademicVerb($item)) { $issues[] = 'specific_objective_weak'; }
            $key = mb_strtolower($item);
            if (isset($seen[$key])) { $issues[] = 'specific_objective_duplicated'; }
            $seen[$key] = true;
        }

        return ['ok' => $issues === [], 'issues' => array_values(array_unique($issues))];
    }

    private function hasAcademicVerb(string $text): bool
    {
        $verbs = ['analisar', 'avaliar', 'contextualizar', 'descrever', 'examinar', 'discutir', 'comparar', 'interpretar', 'identificar'];
        $lower = mb_strtolower($text);
        foreach ($verbs as $verb) {
            if (str_contains($lower, $verb)) {
                return true;
            }
        }

        return false;
    }
}
