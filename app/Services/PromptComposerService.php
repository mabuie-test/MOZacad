<?php

declare(strict_types=1);

namespace App\Services;

final class PromptComposerService
{
    public function compose(array $blueprint, array $rules, array $briefing): array
    {
        $prompts = [];
        $promptProfileVersion = (string) ($rules['prompt_profile_version'] ?? 'v2.0.0');
        $theme = (string) ($briefing['title'] ?? '');
        $problem = (string) ($briefing['problem'] ?? '');
        $general = (string) ($briefing['generalObjective'] ?? '');
        $specific = implode('; ', (array) ($briefing['specificObjectives'] ?? []));
        $keywords = implode(', ', (array) ($briefing['keywords'] ?? []));
        $genericTemplates = 'No contexto de [tema], [autor/estudo] aborda [assunto] de forma geral.|A presente secção visa apresentar [ponto] no âmbito de [tema].|Conclui-se, de forma ampla, que [resultado] sem detalhar mecanismos.|Importa referir que [assunto] é relevante para [área], sem evidência concreta.';

        $globalContext = "[PERFIL_PROMPT]\n"
            . "prompt_profile_version: {$promptProfileVersion}\n\n"
            . "[CONTEXTO_GLOBAL]\n"
            . "Tema: {$theme}\n"
            . "Problema de investigação: {$problem}\n"
            . "Objectivo geral: {$general}\n"
            . "Objectivos específicos: {$specific}\n"
            . "Palavras-chave do projecto: {$keywords}\n"
            . "Este contexto é permanente para todas as secções; não repetir mecanicamente problema/objectivos fora das secções em que isso seja contrato explícito.";

        foreach ($blueprint as $i => $section) {
            $title = (string) ($section['title'] ?? 'Secção');
            $prev = (string) (($blueprint[$i-1]['title'] ?? 'nenhuma'));
            $next = (string) (($blueprint[$i+1]['title'] ?? 'nenhuma'));
            $contract = $this->sectionContract($title);
            $terms = $this->mandatoryTerms($theme, $title, $briefing);
            $prompts[] = $globalContext . "\n\n"
                . "[INSTRUCAO_SECCAO]\n"
                . "Escreve apenas o corpo da secção '{$title}' em português académico de Moçambique.\n"
                . "Secção anterior: {$prev}; secção seguinte: {$next}.\n"
                . "Contrato da secção: {$contract}.\n"
                . "Para secções não introdutórias, não re-enunciar explicitamente problema/objectivos, excepto se necessário para coerência analítica pontual.\n"
                . "Evita repetir frases de abertura já usadas noutras secções; inicia esta secção com formulação própria e foco substantivo distinto.\n"
                . "Cada parágrafo deve introduzir informação nova, verificável e específica do foco desta secção.\n"
                . "Valida internamente fidelidade aos objectivos específicos relevantes da secção sem replicar blocos textuais longos dos objectivos.\n"
                . "Instrução anti-fórmulas: não usar padrões semânticos genéricos/placeholder como '{$genericTemplates}'. Reformulação obrigatória: sempre que surgir formulação vaga, substitui imediatamente por redação específica com conceitos, relações causais, recorte temporal/espacial e evidência concreta.\n"
                . "Termos obrigatórios contextuais desta secção: {$terms}.\n"
                . "Proibido usar Markdown, referências inventadas ou texto meta-editorial.";
        }

        return $prompts;
    }

    private function sectionContract(string $title): string
    {
        $t = mb_strtolower($title);

        if (str_contains($t, 'introdu')) {
            return 'apresentar problema de investigação, objectivo geral, objectivos específicos e delimitação do estudo. Exemplo aceitável: "Este estudo analisa como a expansão do ensino colonial em Nampula (1930-1974) estruturou desigualdades de acesso escolar entre grupos africanos e assimilados." Exemplo a evitar: "No contexto do tema, a investigação apresenta o problema de forma geral."';
        }

        if (str_contains($t, 'metodolog')) {
            return 'explicitar desenho metodológico, procedimentos de recolha/tratamento de dados, critérios de validade e limitações. Exemplo aceitável: "Adoptou-se estudo qualitativo documental com análise temática de relatórios coloniais (1945-1973) e triangulação com legislação educativa." Exemplo a evitar: "A metodologia foi adequada e permitiu analisar os dados."';
        }

        if (str_contains($t, 'conclus')) {
            return 'sintetizar resultados centrais, implicações teóricas/práticas e recomendações consistentes com os achados. Exemplo aceitável: "Os achados indicam que a segmentação curricular colonial ampliou diferenciais de mobilidade social, exigindo políticas de reparação centradas em inclusão linguística e financiamento escolar periférico." Exemplo a evitar: "Conclui-se que o tema é importante e deve ser mais estudado."';
        }

        return 'desenvolver apenas o propósito específico desta secção com progressão lógica e coerência argumentativa. Exemplo aceitável: "A secção demonstra como a política de recrutamento docente afectou a qualidade do ensino rural entre 1955 e 1970, relacionando escassez de formação e abandono escolar." Exemplo a evitar: "Esta parte aborda alguns aspectos relevantes do tema."';
    }

    private function mandatoryTerms(string $theme, string $title, array $briefing): string
    {
        $t = mb_strtolower($theme . ' ' . $title);
        if (str_contains($t, 'moçambique') && str_contains($t, 'colon')) {
            return 'colonialismo português, administração colonial, estatuto do indigenato, missões religiosas, ensino rudimentar, língua portuguesa, currículo colonial, desigualdade de acesso, formação laboral, legado pós-independência';
        }

        $sectionSpecific = [];
        $keywords = (array) ($briefing['keywords'] ?? []);
        $specificObjectives = (array) ($briefing['specificObjectives'] ?? []);
        $maxTerms = 8;

        foreach ($specificObjectives as $objective) {
            $objectiveText = trim((string) $objective);
            if ($objectiveText === '') {
                continue;
            }

            $phrases = $this->extractObjectivePhrases($objectiveText);
            foreach ($phrases as $phrase) {
                $score = $this->relevanceScore($phrase, $title, $theme);
                if ($score > 0) {
                    $sectionSpecific[] = ['term' => $phrase, 'score' => $score + 3];
                }
            }
        }

        foreach ($keywords as $keyword) {
            $k = trim((string) $keyword);
            if ($k === '') {
                continue;
            }

            $score = $this->relevanceScore($k, $title, $theme);
            if ($score > 0) {
                $sectionSpecific[] = ['term' => $k, 'score' => $score];
            }
        }

        if ($sectionSpecific !== []) {
            usort(
                $sectionSpecific,
                static fn (array $a, array $b): int => $b['score'] <=> $a['score']
            );

            $orderedTerms = [];
            $fingerprints = [];
            foreach ($sectionSpecific as $entry) {
                $term = $this->normalizeTermLength((string) $entry['term']);
                $fingerprint = $this->termFingerprint($term);
                if ($term === '' || isset($fingerprints[$fingerprint])) {
                    continue;
                }

                $orderedTerms[] = $term;
                $fingerprints[$fingerprint] = true;
                if (count($orderedTerms) >= $maxTerms) {
                    break;
                }
            }

            if ($orderedTerms !== []) {
                return implode(', ', $orderedTerms);
            }
        }

        if (str_contains($t, 'metodolog')) {
            return 'desenho de pesquisa, amostra/unidade de análise, técnicas de recolha de dados, critérios de validade';
        }

        if (str_contains($t, 'conclus')) {
            return 'síntese dos achados, implicações, limitações, recomendações';
        }

        return 'termos nucleares estritamente ligados ao tema e ao objectivo específico da secção';
    }

    private function extractObjectivePhrases(string $objective): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($objective)) ?? '';
        if ($normalized === '') {
            return [];
        }

        $chunks = preg_split('/[;,:()\-]+/u', $normalized) ?: [];
        $phrases = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $clean = preg_replace('/\b(identificar|analisar|avaliar|compreender|examinar|investigar|determinar|comparar|descrever|verificar|medir|explicar)\b/iu', '', $chunk) ?? '';
            $clean = preg_replace('/\b(o|a|os|as|de|da|do|das|dos|e|em|no|na|nos|nas|para|por|com|sobre)\b/iu', ' ', $clean) ?? '';
            $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? '';
            $clean = $this->normalizeTermLength($clean);

            if ($clean !== '' && mb_strlen($clean) >= 6) {
                $phrases[] = $clean;
            }
        }

        if ($phrases === []) {
            $fallback = $this->normalizeTermLength($normalized);
            return $fallback === '' ? [] : [$fallback];
        }

        return array_values(array_unique($phrases));
    }

    private function normalizeTermLength(string $term): string
    {
        $words = preg_split('/\s+/u', trim($term)) ?: [];
        $words = array_values(array_filter($words, static fn (string $w): bool => $w !== ''));
        if (count($words) > 5) {
            $words = array_slice($words, 0, 5);
        }

        return implode(' ', $words);
    }

    private function termFingerprint(string $term): string
    {
        $base = mb_strtolower($term);
        $base = preg_replace('/[^[:alnum:]\s]/u', ' ', $base) ?? '';
        $base = preg_replace('/\s+/u', ' ', trim($base)) ?? '';

        $tokens = preg_split('/\s+/u', $base) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => mb_strlen($token) > 2));
        sort($tokens);

        return implode('|', $tokens);
    }

    private function relevanceScore(string $term, string $title, string $theme): int
    {
        $score = 0;
        $termLower = mb_strtolower($term);
        $titleLower = mb_strtolower($title);
        $themeLower = mb_strtolower($theme);

        if (str_contains($titleLower, $termLower)) {
            $score += 5;
        }

        $tokens = preg_split('/[\s,;:.!?()\-\_\/]+/u', $termLower) ?: [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || mb_strlen($token) < 4) {
                continue;
            }

            if (str_contains($titleLower, $token)) {
                $score += 3;
            }

            if (str_contains($themeLower, $token)) {
                $score += 1;
            }
        }

        if ($score === 0 && mb_strlen($termLower) > 10) {
            $score = 1;
        }

        return $score;
    }
}
