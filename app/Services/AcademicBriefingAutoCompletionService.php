<?php
declare(strict_types=1);
namespace App\Services;
use App\Support\UnicodeWordCounter;

final class AcademicBriefingAutoCompletionService
{
    private AIProviderInterface $provider;

    public function __construct(?AIProviderInterface $provider = null)
    {
        $this->provider = $provider ?? (new AIProviderResolverService())->resolve();
    }

    public function complete(array $order, array $requirements, array $context): array
    {
        $title         = trim((string) ($order['topic'] ?? $order['title'] ?? $requirements['title_or_theme'] ?? 'tema académico'));
        $briefing      = trim((string) ($requirements['briefing'] ?? $order['briefing'] ?? ''));
        $workType      = $this->normalize((string) ($order['work_type_slug'] ?? $order['work_type_name'] ?? $requirements['work_type_slug'] ?? $requirements['work_type_name'] ?? ''));
        $workTypeName  = trim((string) ($order['work_type_name'] ?? $requirements['work_type_name'] ?? $workType));
        $institution   = trim((string) ($order['institution_name'] ?? $requirements['institution_name'] ?? ''));
        $course        = trim((string) ($order['course_name'] ?? $requirements['course_name'] ?? ''));
        $academicLevel = trim((string) ($order['academic_level_name'] ?? $requirements['academic_level_name'] ?? ''));

        $problem  = trim((string) ($requirements['problem_statement'] ?? $order['problem_statement'] ?? ''));
        $general  = trim((string) ($requirements['general_objective'] ?? $order['general_objective'] ?? ''));
        $specific = $this->toList($requirements['specific_objectives_json'] ?? $order['specific_objectives_json'] ?? []);
        $keywords = $this->toList($requirements['keywords_json'] ?? $order['keywords_json'] ?? []);

        $aiEnabled     = strtolower(trim((string) ($_ENV['BRIEFING_AUTOCOMPLETE_PROVIDER'] ?? 'ai'))) === 'ai'
                         && (bool) ($_ENV['BRIEFING_AUTOCOMPLETE_ENABLED'] ?? true);
        $minSpecific   = (int) ($_ENV['BRIEFING_AUTOCOMPLETE_MIN_SPECIFIC_OBJECTIVES'] ?? 3);
        $maxSpecific   = (int) ($_ENV['BRIEFING_AUTOCOMPLETE_MAX_SPECIFIC_OBJECTIVES'] ?? 5);

        $needsProblem  = $problem === '';
        $needsGeneral  = $general === '' || UnicodeWordCounter::count($general) < 6;
        $needsSpecific = count($specific) < $minSpecific;
        $needsKeywords = $keywords === [];

        $warnings     = [];
        $inferred     = false;
        $providerUsed = 'heuristic';

        // ── 1. Inferência via IA (structured output) ──────────────────────
        if ($aiEnabled && ($needsProblem || $needsGeneral || $needsSpecific || $needsKeywords)) {
            try {
                $aiResult = $this->inferViaAI(
                    $title, $briefing, $workTypeName, $institution, $course, $academicLevel,
                    $needsProblem, $needsGeneral, $needsSpecific, $needsKeywords
                );

                if ($needsProblem && !empty($aiResult['problem_statement'])) {
                    $problem = trim((string) $aiResult['problem_statement']);
                }
                if ($needsGeneral && !empty($aiResult['general_objective'])) {
                    $general = trim((string) $aiResult['general_objective']);
                }
                if ($needsSpecific && !empty($aiResult['specific_objectives']) && is_array($aiResult['specific_objectives'])) {
                    $specific = array_values(array_filter(
                        array_map(static fn ($v) => trim((string) $v), $aiResult['specific_objectives']),
                        static fn ($v) => $v !== ''
                    ));
                }
                if ($needsKeywords && !empty($aiResult['keywords']) && is_array($aiResult['keywords'])) {
                    $keywords = array_values(array_filter(
                        array_map(static fn ($v) => trim((string) $v), $aiResult['keywords']),
                        static fn ($v) => $v !== ''
                    ));
                }

                $inferred     = true;
                $providerUsed = 'ai';
            } catch (\Throwable $e) {
                $warnings[] = 'ai_autocomplete_failed:' . $e->getMessage();
            }
        }

        // ── 2. Fallback heurístico para campos ainda em falta ─────────────
        $defaults                  = $this->templateByWorkType($workType, $title);
        $educationColonialApplied  = $this->matchesColonialEducationProfile($title, $briefing);

        if ($problem === '')                                            $problem  = $defaults['problem_statement'];
        if ($general === '' || UnicodeWordCounter::count($general) < 6) $general  = $defaults['general_objective'];
        if (count($specific) < $minSpecific)                            $specific = $defaults['specific_objectives'];
        if ($keywords === [])                                           $keywords = $this->extractFallbackKeywords($title);

        if ($educationColonialApplied) {
            $keywords = array_values(array_unique(array_merge(
                $keywords,
                ['educação colonial', 'Moçambique colonial', 'missões religiosas', 'assimilação', 'ensino rudimentar']
            )));
        }

        if (count($specific) > $maxSpecific) {
            $specific = array_slice($specific, 0, $maxSpecific);
        }

        return [
            'problem_statement'   => $problem,
            'general_objective'   => $general,
            'specific_objectives' => $specific,
            'keywords'            => $keywords,
            'applied_profile'     => [
                'work_type_template'         => $defaults['profile'],
                'education_colonial_package' => $educationColonialApplied,
            ],
            'inferred_by_ai' => $inferred,
            'provider'       => $providerUsed,
            'confidence'     => $inferred ? 'high' : 'medium',
            'warnings'       => $warnings,
        ];
    }

    // ── Inferência via IA ────────────────────────────────────────────────

    private function inferViaAI(
        string $title, string $briefing, string $workTypeName,
        string $institution, string $course, string $academicLevel,
        bool $needsProblem, bool $needsGeneral, bool $needsSpecific, bool $needsKeywords
    ): array {
        $fields = [];
        if ($needsProblem)  $fields[] = '"problem_statement": string — problema de investigação claro e delimitado (1-2 frases)';
        if ($needsGeneral)  $fields[] = '"general_objective": string — objectivo geral com verbo de acção, objecto analítico e âmbito (1 frase)';
        if ($needsSpecific) $fields[] = '"specific_objectives": array[string] — 3 a 5 objectivos específicos, cada um com verbo, objecto e recorte metodológico/espacial/temporal';
        if ($needsKeywords) $fields[] = '"keywords": array[string] — 5 a 8 palavras-chave académicas representativas do tema';

        $ctx = implode("\n", array_filter([
            "Tema / Título: {$title}",
            $briefing      !== '' ? "Briefing do estudante: {$briefing}"   : '',
            $workTypeName  !== '' ? "Tipo de trabalho: {$workTypeName}"    : '',
            $institution   !== '' ? "Instituição: {$institution}"          : '',
            $course        !== '' ? "Curso: {$course}"                     : '',
            $academicLevel !== '' ? "Nível académico: {$academicLevel}"    : '',
        ]));

        $fieldList = implode("\n", array_map(static fn ($f) => "  - {$f}", $fields));

        $prompt = <<<PROMPT
És um especialista em metodologia de investigação académica moçambicana.
Com base nas informações do trabalho abaixo, gera APENAS os campos solicitados em JSON válido.
Responde exclusivamente com o objecto JSON, sem texto antes ou depois, sem blocos de código Markdown.

{$ctx}

Campos a gerar:
{$fieldList}

Critérios obrigatórios:
- Linguagem em português académico formal de Moçambique (pt_MZ).
- Objectivos específicos operacionalizáveis, alinhados ao objectivo geral.
- Cada objectivo específico inicia com verbo de acção (Analisar, Identificar, Descrever, Avaliar, Comparar, etc.).
- Problema de investigação formulado como questão ou afirmação de lacuna verificável.
- Palavras-chave cobrem tema, metodologia e contexto geográfico/temporal quando aplicável.
- Não usar frases genéricas do tipo "o estudo abordará aspectos relevantes".
PROMPT;

        $schemaProps = array_merge(
            $needsProblem  ? ['problem_statement'   => ['type' => 'string']] : [],
            $needsGeneral  ? ['general_objective'   => ['type' => 'string']] : [],
            $needsSpecific ? ['specific_objectives' => ['type' => 'array', 'items' => ['type' => 'string']]] : [],
            $needsKeywords ? ['keywords'            => ['type' => 'array', 'items' => ['type' => 'string']]] : [],
        );

        return $this->provider->generateStructured($prompt, ['type' => 'object', 'properties' => $schemaProps]);
    }

    // ── Templates heurísticos de fallback ───────────────────────────────

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
        $tokens     = preg_split('/[^a-z0-9]+/u', $normalized) ?: [];
        $stopwords  = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'para', 'por', 'com', 'sobre', 'uma', 'um', 'na', 'no'];
        $terms      = [];
        foreach ($tokens as $token) {
            if ($token === '' || mb_strlen($token) < 4 || in_array($token, $stopwords, true)) continue;
            $terms[] = $token;
        }
        $keywords = array_slice(array_values(array_unique($terms)), 0, 6);
        return $keywords !== [] ? $keywords : ['tema académico'];
    }

    private function matchesColonialEducationProfile(string $title, string $briefing): bool
    {
        $h = $this->normalize($title . ' ' . $briefing);
        return $this->containsAny($h, ['colonial', 'colonia', 'ultramar'])
            && $this->containsAny($h, ['educacao', 'ensino', 'escola', 'escolar', 'pedagog']);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) { if (str_contains($haystack, $n)) return true; }
        return false;
    }

    private function toList(mixed $raw): array
    {
        if (is_string($raw)) { $decoded = json_decode($raw, true); if (is_array($decoded)) $raw = $decoded; }
        if (!is_array($raw)) return [];
        return array_values(array_filter(array_map(static fn ($i) => trim((string) $i), $raw), static fn ($v) => $v !== ''));
    }

    private function normalize(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return strtolower(is_string($ascii) ? $ascii : $text);
    }
}