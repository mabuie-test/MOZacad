<?php
declare(strict_types=1);
namespace App\Services;

final class PromptComposerService
{
    /**
     * @param array $blueprint  Secções do documento
     * @param array $rules      Regras resolvidas (visualRules, referenceRules, meta)
     * @param array $briefing   Briefing completo (title, problem, generalObjective, specificObjectives, keywords)
     */
    public function compose(array $blueprint, array $rules, array $briefing): array
    {
        $prompts             = [];
        $promptProfileVersion = (string) ($rules['prompt_profile_version'] ?? 'v2.1.0');

        $theme    = (string) ($briefing['title'] ?? '');
        $problem  = (string) ($briefing['problem'] ?? '');
        $general  = (string) ($briefing['generalObjective'] ?? '');
        $specific = implode('; ', (array) ($briefing['specificObjectives'] ?? []));
        $keywords = implode(', ', (array) ($briefing['keywords'] ?? []));

        // ── Norma institucional injectada no contexto global ──────────────
        $normExcerpt   = '';
        $normSource    = '';
        $referenceStyle = strtoupper(trim((string) ($rules['referenceRules']['style'] ?? 'APA')));

        $normMeta = $rules['meta']['institution_norm'] ?? [];
        if (is_array($normMeta)) {
            $rawExcerpt  = trim((string) ($normMeta['excerpt'] ?? ''));
            $normSource  = (string) ($normMeta['source'] ?? 'none');
            if ($rawExcerpt !== '' && $normSource !== 'none') {
                // Limitar a 1500 chars para não inflar desnecessariamente o prompt
                $normExcerpt = mb_substr($rawExcerpt, 0, 1500);
            }
        }

        // ── Metadados visuais da norma para o prompt ─────────────────────
        $visualRules = is_array($rules['visualRules'] ?? null) ? $rules['visualRules'] : [];
        $fontFamily  = (string) ($visualRules['font_family']  ?? 'Times New Roman');
        $fontSize    = (string) ($visualRules['font_size']    ?? '12');
        $lineSpacing = (string) ($visualRules['line_spacing'] ?? '1.5');
        $margins     = is_array($visualRules['margins'] ?? null) ? $visualRules['margins'] : [];
        $marginBlock = '';
        if (!empty($margins)) {
            $marginBlock = sprintf(
                'Margens: superior %.1fcm, inferior %.1fcm, esquerda %.1fcm, direita %.1fcm.',
                (float) ($margins['top']    ?? 2.5),
                (float) ($margins['bottom'] ?? 2.5),
                (float) ($margins['left']   ?? 3.0),
                (float) ($margins['right']  ?? 3.0)
            );
        }

        $genericTemplates = 'No contexto de [tema], [autor/estudo] aborda [assunto] de forma geral.|A presente secção visa apresentar [ponto] no âmbito de [tema].|Conclui-se, de forma ampla, que [resultado] sem detalhar mecanismos.|Importa referir que [assunto] é relevante para [área], sem evidência concreta.';

        // ── Bloco de contexto global (enviado em TODOS os prompts) ────────
        $globalContext  = "[PERFIL_PROMPT]\n"
            . "prompt_profile_version: {$promptProfileVersion}\n\n"
            . "[CONTEXTO_GLOBAL]\n"
            . "Tema: {$theme}\n"
            . "Problema de investigação: {$problem}\n"
            . "Objectivo geral: {$general}\n"
            . "Objectivos específicos: {$specific}\n"
            . "Palavras-chave do projecto: {$keywords}\n"
            . "Este contexto é permanente para todas as secções; não repetir mecanicamente problema/objectivos fora das secções em que isso seja contrato explícito.\n\n"
            . "[NORMAS_INSTITUCIONAIS]\n"
            . "Norma de referências bibliográficas: {$referenceStyle}\n"
            . "Fonte da norma: {$normSource}\n"
            . "Formatação do documento: fonte {$fontFamily} {$fontSize}pt, espaçamento {$lineSpacing}. {$marginBlock}\n"
            . ($normExcerpt !== ''
                ? "Excerto da norma institucional (cumprimento obrigatório):\n{$normExcerpt}\n"
                : "Norma institucional em texto não disponível — aplicar boas práticas académicas padrão.\n")
            . "\n[CITACOES]\n"
            . "Estilo de citação obrigatório no corpo do texto: {$referenceStyle}.\n"
            . "Para citações directas: incluir autor, ano e página ex: (Apelido, Ano, p. X).\n"
            . "Para citações indirectas/paráfrase: (Apelido, Ano).\n"
            . "Cada secção de desenvolvimento deve conter no mínimo 3 citações integradas no texto.\n"
            . "NUNCA inventar autores, títulos ou anos — usar apenas referências plausíveis e genéricas se necessário, marcadas com [VERIFICAR].\n";

        foreach ($blueprint as $i => $section) {
            $title    = (string) ($section['title'] ?? 'Secção');
            $prev     = (string) ($blueprint[$i - 1]['title'] ?? 'nenhuma');
            $next     = (string) ($blueprint[$i + 1]['title'] ?? 'nenhuma');
            $minWords = (int) ($section['min_words'] ?? 0);
            $maxWords = (int) ($section['max_words'] ?? 0);
            $contract = $this->sectionContract($title, $referenceStyle);
            $terms    = $this->mandatoryTerms($theme, $title, $briefing);

            $wordConstraint = '';
            if ($minWords > 0 && $maxWords > 0) {
                $wordConstraint = "Extensão obrigatória: entre {$minWords} e {$maxWords} palavras.\n";
            } elseif ($minWords > 0) {
                $wordConstraint = "Extensão mínima obrigatória: {$minWords} palavras.\n";
            }

            $prompts[] = $globalContext . "\n"
                . "[INSTRUCAO_SECCAO]\n"
                . "Escreve apenas o corpo da secção '{$title}' em português académico formal de Moçambique (pt_MZ).\n"
                . "Secção anterior: {$prev}; secção seguinte: {$next}.\n"
                . $wordConstraint
                . "Contrato da secção: {$contract}\n"
                . "Para secções não introdutórias, não re-enunciar explicitamente problema/objectivos, excepto se necessário para coerência analítica pontual.\n"
                . "Evita repetir frases de abertura já usadas noutras secções; inicia esta secção com formulação própria e foco substantivo distinto.\n"
                . "Cada parágrafo deve introduzir informação nova, verificável e específica do foco desta secção.\n"
                . "Para secções de desenvolvimento/análise: incluir confronto explícito entre autores, enquadramento histórico-crítico e leitura sociopolítica com marcadores temporais/espaciais concretos.\n"
                . "Para a secção de metodologia: especificar obrigatoriamente tipo de pesquisa, abordagem (qualitativa/quantitativa/mista), instrumento de recolha de dados, universo/amostra e técnica de análise.\n"
                . "Termos obrigatórios contextuais desta secção: {$terms}\n"
                . "PROIBIDO: Markdown, listas com bullet/asterisco, referências inventadas, texto meta-editorial, frases genéricas placeholder como: '{$genericTemplates}'.";
        }

        return $prompts;
    }

    // ── Contratos por tipo de secção ─────────────────────────────────────

    private function sectionContract(string $title, string $referenceStyle = 'APA'): string
    {
        $t = mb_strtolower($title);

        if (str_contains($t, 'resumo') || str_contains($t, 'abstract')) {
            return 'Apresentar obrigatoriamente 6 elementos: (1) tema delimitado, (2) problema de investigação, (3) objectivo geral, (4) metodologia adoptada, (5) síntese dos achados principais, (6) palavras-chave. '
                . 'Extensão: 150-250 palavras. Sem citações. Sem parágrafos separados — texto corrido. '
                . 'Exemplo aceitável: "O presente estudo analisa [tema] em [contexto/período], tendo como problema [problema]. Objectivou-se [objectivo geral]. Adoptou-se [metodologia]. Os resultados indicam [achados]. Palavras-chave: [lista]." '
                . 'Exemplo a EVITAR: "O resumo apresenta os pontos principais do trabalho de forma geral."';
        }

        if (str_contains($t, 'introdu')) {
            return 'Apresentar: (1) contextualização do tema com dados/factos concretos, (2) problema de investigação formulado claramente, (3) justificativa da relevância académica e social, (4) objectivo geral, (5) objectivos específicos listados, (6) estrutura do trabalho. '
                . 'Exemplo aceitável: "Este estudo analisa como a expansão do ensino em [local/período] estruturou desigualdades de acesso entre [grupos]. O problema centra-se em [problema específico]. A investigação justifica-se por [razão]. O objectivo geral é [objectivo]. Os objectivos específicos são: (i) [...], (ii) [...], (iii) [...]." '
                . 'Exemplo a EVITAR: "No contexto do tema, a investigação apresenta o problema de forma geral."';
        }

        if (str_contains($t, 'metod')) {
            return 'Especificar obrigatoriamente: (1) tipo de pesquisa (exploratória/descritiva/explicativa), (2) abordagem (qualitativa/quantitativa/mista), (3) método (estudo de caso/survey/documental/experimental), (4) instrumentos de recolha de dados (entrevista/questionário/análise documental), (5) universo e critérios de selecção da amostra, (6) técnica de análise de dados (análise de conteúdo/estatística descritiva/análise temática), (7) limitações metodológicas. '
                . 'Exemplo aceitável: "Adoptou-se pesquisa qualitativa descritiva com método documental. A recolha de dados baseou-se em análise de [X documentos/fontes] do período [ano-ano]. A amostra foi seleccionada por [critério]. Os dados foram analisados via análise temática (Bardin, 2011)." '
                . 'Exemplo a EVITAR: "A metodologia foi adequada e permitiu analisar os dados."';
        }

        if (str_contains($t, 'conclus') || str_contains($t, 'consideraç')) {
            return 'Sintetizar: (1) resposta directa ao problema de investigação, (2) confirmação/refutação dos objectivos específicos, (3) principais contributos teóricos e práticos, (4) limitações do estudo, (5) recomendações para estudos futuros. '
                . 'SEM novas citações ou argumentos não desenvolvidos anteriormente. '
                . 'Exemplo aceitável: "Os achados confirmam que [resultado central], respondendo ao problema de investigação. O objectivo [X] foi atingido ao demonstrar [resultado]. O estudo contribui para [área] ao evidenciar [contributo]. As limitações incluem [limitação]. Recomenda-se [recomendação]." '
                . 'Exemplo a EVITAR: "Conclui-se que o tema é importante e deve ser mais estudado."';
        }

        if (str_contains($t, 'refer') || str_contains($t, 'bibliograf')) {
            return "Listar APENAS as fontes efectivamente citadas no texto, formatadas em {$referenceStyle}. "
                . 'Ordenação alfabética por apelido do primeiro autor. Entradas com recuo suspenso (hanging indent). '
                . "Formato {$referenceStyle} para livro: Apelido, Iniciais. (Ano). Título em itálico. Editora. "
                . "Formato {$referenceStyle} para artigo: Apelido, Iniciais. (Ano). Título do artigo. Nome da Revista em itálico, Volume(Número), pp. xx-xx. DOI/URL. "
                . 'NÃO incluir fontes não citadas no texto.';
        }

        return 'Desenvolver o propósito específico desta secção com progressão lógica, coerência argumentativa e densidade conceptual. '
            . 'Incluir confronto entre perspectivas de diferentes autores, dados empíricos ou exemplos concretos, e articulação com os objectivos do trabalho. '
            . 'Exemplo aceitável: "A secção demonstra como [argumento central] ao analisar [aspecto específico], relacionando [conceito A] com [conceito B] à luz de [autor, ano] e contrastando com [outro autor, ano]." '
            . 'Exemplo a EVITAR: "Esta parte aborda alguns aspectos relevantes do tema."';
    }

    // ── Termos obrigatórios por secção ───────────────────────────────────

    private function mandatoryTerms(string $theme, string $title, array $briefing): string
    {
        $t = mb_strtolower($theme . ' ' . $title);

        if (str_contains($t, 'moçambique') && str_contains($t, 'colon')) {
            return 'colonialismo português, administração colonial, estatuto do indigenato, missões religiosas, ensino rudimentar, língua portuguesa, currículo colonial, desigualdade de acesso, formação laboral, legado pós-independência';
        }

        $sectionSpecific = [];
        $keywords        = (array) ($briefing['keywords'] ?? []);
        $specificObjs    = (array) ($briefing['specificObjectives'] ?? []);
        $maxTerms        = 8;

        foreach ($specificObjs as $obj) {
            $objText = trim((string) $obj);
            if ($objText === '') continue;
            foreach ($this->extractObjectivePhrases($objText) as $phrase) {
                $score = $this->relevanceScore($phrase, $title, $theme);
                if ($score > 0) $sectionSpecific[] = ['term' => $phrase, 'score' => $score + 3];
            }
        }
        foreach ($keywords as $kw) {
            $k = trim((string) $kw);
            if ($k === '') continue;
            $score = $this->relevanceScore($k, $title, $theme);
            if ($score > 0) $sectionSpecific[] = ['term' => $k, 'score' => $score];
        }

        if ($sectionSpecific !== []) {
            usort($sectionSpecific, static fn ($a, $b) => $b['score'] <=> $a['score']);
            $ordered = [];
            $seen    = [];
            foreach ($sectionSpecific as $entry) {
                $term = $this->normalizeTermLength((string) $entry['term']);
                $fp   = $this->termFingerprint($term);
                if ($term === '' || isset($seen[$fp])) continue;
                $ordered[] = $term;
                $seen[$fp] = true;
                if (count($ordered) >= $maxTerms) break;
            }
            if ($ordered !== []) return implode(', ', $ordered);
        }

        if (str_contains($t, 'metodolog')) return 'desenho de pesquisa, amostra/unidade de análise, técnicas de recolha de dados, critérios de validade';
        if (str_contains($t, 'conclus'))  return 'síntese dos achados, implicações, limitações, recomendações';

        return 'termos nucleares estritamente ligados ao tema e ao objectivo específico da secção';
    }

    // ── Utilitários ───────────────────────────────────────────────────────

    private function extractObjectivePhrases(string $objective): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($objective)) ?? '';
        if ($normalized === '') return [];
        $chunks = preg_split('/[;,:()-]+/u', $normalized) ?: [];
        $phrases = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;
            $clean = preg_replace('/\b(identificar|analisar|avaliar|compreender|examinar|investigar|determinar|comparar|descrever|verificar|medir|explicar)\b/iu', '', $chunk) ?? '';
            $clean = preg_replace('/\b(o|a|os|as|de|da|do|das|dos|e|em|no|na|nos|nas|para|por|com|sobre)\b/iu', ' ', $clean) ?? '';
            $clean = $this->normalizeTermLength(trim(preg_replace('/\s+/u', ' ', $clean) ?? ''));
            if ($clean !== '' && mb_strlen($clean) >= 6) $phrases[] = $clean;
        }
        if ($phrases === []) {
            $fb = $this->normalizeTermLength($normalized);
            return $fb === '' ? [] : [$fb];
        }
        return array_values(array_unique($phrases));
    }

    private function normalizeTermLength(string $term): string
    {
        $words = array_values(array_filter(preg_split('/\s+/u', trim($term)) ?: [], static fn ($w) => $w !== ''));
        if (count($words) > 5) $words = array_slice($words, 0, 5);
        return implode(' ', $words);
    }

    private function termFingerprint(string $term): string
    {
        $base   = preg_replace('/[^[:alnum:]\s]/u', ' ', mb_strtolower($term)) ?? '';
        $base   = preg_replace('/\s+/u', ' ', trim($base)) ?? '';
        $tokens = array_values(array_filter(preg_split('/\s+/u', $base) ?: [], static fn ($t) => mb_strlen($t) > 2));
        sort($tokens);
        return implode('|', $tokens);
    }

    private function relevanceScore(string $term, string $title, string $theme): int
    {
        $score      = 0;
        $termLower  = mb_strtolower($term);
        $titleLower = mb_strtolower($title);
        $themeLower = mb_strtolower($theme);
        if (str_contains($titleLower, $termLower)) $score += 5;
        $tokens = preg_split('/[\s,;:.!?()\-\_\/]+/u', $termLower) ?: [];
        foreach ($tokens as $tok) {
            $tok = trim($tok);
            if ($tok === '' || mb_strlen($tok) < 4) continue;
            if (str_contains($titleLower, $tok)) $score += 3;
            if (str_contains($themeLower, $tok)) $score += 1;
        }
        if ($score === 0 && mb_strlen($termLower) > 10) $score = 1;
        return $score;
    }
}