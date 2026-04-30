<?php

declare(strict_types=1);

namespace App\Services;

final class AcademicBriefingAutoCompletionService
{
    public function complete(array $order, array $requirements, array $context): array
    {
        $title = trim((string) ($order['topic'] ?? $order['title'] ?? $requirements['title_or_theme'] ?? 'tema académico'));
        $problem = trim((string) ($requirements['problem_statement'] ?? $order['problem_statement'] ?? ''));
        $general = trim((string) ($requirements['general_objective'] ?? $order['general_objective'] ?? ''));
        $specific = $this->toList($requirements['specific_objectives_json'] ?? $order['specific_objectives_json'] ?? []);
        $keywords = $this->toList($requirements['keywords_json'] ?? $order['keywords_json'] ?? []);

        if ($problem === '') {
            $problem = "Como se estruturou {$title} e que efeitos produziu no contexto moçambicano?";
        }
        if ($general === '' || str_word_count($general) < 6) {
            $general = "Analisar {$title}, considerando políticas, actores institucionais, desigualdades de acesso e legados sociais.";
        }
        if (count($specific) < (int) ($_ENV['BRIEFING_AUTOCOMPLETE_MIN_SPECIFIC_OBJECTIVES'] ?? 3)) {
            $specific = [
                "Contextualizar historicamente {$title}.",
                'Descrever actores institucionais, normas e práticas educativas.',
                'Analisar desigualdades sociais, raciais e linguísticas no acesso ao ensino.',
                'Discutir impactos e legados no período pós-independência.',
            ];
        }
        if ($keywords === []) {
            $keywords = ['educação colonial', 'Moçambique colonial', 'missões religiosas', 'assimilação', 'ensino rudimentar'];
        }

        return [
            'problem_statement' => $problem,
            'general_objective' => $general,
            'specific_objectives' => $specific,
            'keywords' => $keywords,
            'inferred_by_ai' => false,
            'provider' => 'heuristic',
            'confidence' => 'medium',
            'warnings' => [],
        ];
    }

    private function toList(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) { $raw = $decoded; }
        }
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn ($i) => trim((string) $i), $raw), static fn ($v) => $v !== ''));
    }
}
