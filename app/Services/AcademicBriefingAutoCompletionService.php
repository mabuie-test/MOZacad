<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\UnicodeWordCounter;

final class AcademicBriefingAutoCompletionService
{
    public function complete(array $order, array $requirements, array $context): array
    {
        $title = trim((string) ($order['topic'] ?? $order['title'] ?? $requirements['title_or_theme'] ?? 'tema académico'));
        $briefing = trim((string) ($requirements['briefing'] ?? $order['briefing'] ?? ''));
        $workType = $this->normalize((string) ($order['work_type_slug'] ?? $order['work_type_name'] ?? $requirements['work_type_slug'] ?? $requirements['work_type_name'] ?? ''));
        $problem = trim((string) ($requirements['problem_statement'] ?? $order['problem_statement'] ?? ''));
        $general = trim((string) ($requirements['general_objective'] ?? $order['general_objective'] ?? ''));
        $specific = $this->toList($requirements['specific_objectives_json'] ?? $order['specific_objectives_json'] ?? []);
        $keywords = $this->toList($requirements['keywords_json'] ?? $order['keywords_json'] ?? []);
        $defaults = $this->templateByWorkType($workType, $title);
        $educationColonialApplied = $this->matchesColonialEducationProfile($title, $briefing);

        if ($problem === '') {
            $problem = $defaults['problem_statement'];
        }
        if ($general === '' || UnicodeWordCounter::count($general) < 6) {
            $general = $defaults['general_objective'];
        }
        if (count($specific) < (int) ($_ENV['BRIEFING_AUTOCOMPLETE_MIN_SPECIFIC_OBJECTIVES'] ?? 3)) {
            $specific = $defaults['specific_objectives'];
        }
        if ($keywords === []) {
            $keywords = $this->extractFallbackKeywords($title);
        }

        if ($educationColonialApplied) {
            $keywords = array_values(array_unique(array_merge(
                $keywords,
                ['educação colonial', 'Moçambique colonial', 'missões religiosas', 'assimilação', 'ensino rudimentar']
            )));
        }

        return [
            'problem_statement' => $problem,
            'general_objective' => $general,
            'specific_objectives' => $specific,
            'keywords' => $keywords,
            'applied_profile' => [
                'work_type_template' => $defaults['profile'],
                'education_colonial_package' => $educationColonialApplied,
            ],
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

    private function templateByWorkType(string $workType, string $title): array
    {
        return match (true) {
            str_contains($workType, 'revis') => [
                'profile' => 'revisao',
                'problem_statement' => "Que evidências e lacunas emergem na literatura sobre {$title}?",
                'general_objective' => "Sistematizar criticamente a literatura sobre {$title}, destacando consensos, divergências e lacunas de investigação.",
                'specific_objectives' => [
                    "Definir critérios de seleção e análise da literatura sobre {$title}.",
                    'Mapear abordagens teóricas, metodológicas e principais resultados publicados.',
                    'Comparar convergências e divergências entre autores e contextos estudados.',
                    'Identificar lacunas e propor agendas para estudos futuros.',
                ],
            ],
            str_contains($workType, 'ensaio') => [
                'profile' => 'ensaio',
                'problem_statement' => "Como argumentar criticamente sobre {$title} à luz de diferentes perspetivas?",
                'general_objective' => "Construir uma análise argumentativa sobre {$title}, articulando fundamentos conceptuais e posicionamento crítico.",
                'specific_objectives' => [
                    "Delimitar os conceitos centrais que estruturam {$title}.",
                    'Confrontar perspetivas teóricas com exemplos e argumentos relevantes.',
                    'Sustentar um posicionamento crítico com base em referências académicas.',
                    'Discutir implicações práticas e limitações da argumentação proposta.',
                ],
            ],
            str_contains($workType, 'empir') || str_contains($workType, 'campo') => [
                'profile' => 'empirico',
                'problem_statement' => "Que padrões explicam {$title} no contexto observado e quais fatores os influenciam?",
                'general_objective' => "Analisar {$title} com base em evidências empíricas, considerando variáveis, atores e contexto de observação.",
                'specific_objectives' => [
                    "Operacionalizar dimensões analíticas para investigar {$title}.",
                    'Caracterizar participantes, contexto e procedimentos de recolha de dados.',
                    'Examinar os resultados obtidos e a relação entre os fatores identificados.',
                    'Interpretar implicações dos achados para investigação e prática.',
                ],
            ],
            default => [
                'profile' => 'teorico',
                'problem_statement' => "Como se estrutura {$title} no plano conceptual e quais são as suas implicações?",
                'general_objective' => "Analisar {$title} no plano teórico, articulando conceitos, perspetivas e implicações para o campo de estudo.",
                'specific_objectives' => [
                    "Definir os conceitos-chave associados a {$title}.",
                    'Comparar perspetivas teóricas e pressupostos analíticos relevantes.',
                    'Discutir implicações epistemológicas e aplicadas para o tema estudado.',
                    'Sintetizar contributos e limitações da abordagem adotada.',
                ],
            ],
        };
    }

    private function extractFallbackKeywords(string $title): array
    {
        $normalized = $this->normalize($title);
        $tokens = preg_split('/[^a-z0-9]+/u', $normalized) ?: [];
        $stopwords = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'para', 'por', 'com', 'sobre', 'uma', 'um', 'na', 'no'];
        $terms = [];

        foreach ($tokens as $token) {
            if ($token === '' || mb_strlen($token) < 4 || in_array($token, $stopwords, true)) {
                continue;
            }
            $terms[] = $token;
        }

        $keywords = array_slice(array_values(array_unique($terms)), 0, 6);
        return $keywords !== [] ? $keywords : ['tema académico'];
    }

    private function matchesColonialEducationProfile(string $title, string $briefing): bool
    {
        $haystack = $this->normalize($title . ' ' . $briefing);
        $colonialTerms = ['colonial', 'colonia', 'ultramar'];
        $educationTerms = ['educacao', 'ensino', 'escola', 'escolar', 'pedagog'];
        return $this->containsAny($haystack, $colonialTerms) && $this->containsAny($haystack, $educationTerms);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function normalize(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $normalized = is_string($ascii) ? $ascii : $text;
        return strtolower($normalized);
    }

}
