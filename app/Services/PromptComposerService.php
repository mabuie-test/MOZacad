<?php

declare(strict_types=1);

namespace App\Services;

final class PromptComposerService
{
    public function compose(array $blueprint, array $rules, array $briefing): array
    {
        $prompts = [];
        $referenceStyle = (string) ($rules['referenceRules']['style'] ?? 'APA');
        $visualRulesJson = json_encode($rules['visualRules'] ?? [], JSON_UNESCAPED_UNICODE);
        $structureRulesJson = json_encode($rules['structureRules'] ?? [], JSON_UNESCAPED_UNICODE);
        $frontPageJson = json_encode($rules['visualRules']['front_page'] ?? [], JSON_UNESCAPED_UNICODE);
        $templateResolution = json_encode($rules['meta']['template_resolution'] ?? ['mode' => 'programmatic_assembly'], JSON_UNESCAPED_UNICODE);

        $norm = is_array($rules['meta']['institution_norm'] ?? null) ? $rules['meta']['institution_norm'] : [];
        $notes = is_array($rules['meta']['notes'] ?? null) ? $rules['meta']['notes'] : [];

        $problem = $this->nonEmpty((string) ($briefing['problem'] ?? ''), 'Analisar criticamente o tema no contexto académico moçambicano, delimitando causas, implicações e relevância científica.');
        $generalObjective = $this->nonEmpty((string) ($briefing['generalObjective'] ?? ''), 'Desenvolver uma abordagem académica coerente sobre o tema, com rigor teórico e clareza metodológica.');
        $specificObjectives = $this->normalizeList($briefing['specificObjectives'] ?? [], [
            'Identificar conceitos centrais e enquadramentos teóricos aplicáveis ao tema.',
            'Estruturar uma metodologia alinhada aos objectivos da investigação.',
            'Discutir implicações académicas e práticas com linguagem formal e consistente.',
        ]);
        $keywords = $this->normalizeList($briefing['keywords'] ?? [], ['investigação académica', 'metodologia científica', 'contexto moçambicano']);

        foreach ($blueprint as $section) {
            $title = (string) ($section['title'] ?? 'Secção');
            $code = mb_strtolower((string) ($section['code'] ?? 'section'));
            $minWords = (int) ($section['min_words'] ?? 250);
            $maxWords = (int) ($section['max_words'] ?? 800);

            $sectionGuide = $this->sectionGuidance($code, $title, $referenceStyle);

            $prompts[] = trim("Escreve exclusivamente o conteúdo da secção académica em português de Moçambique, com fluidez humana e formalidade universitária.\n\n"
                . "Código: {$code}; Título: {$title}; Limite: {$minWords}-{$maxWords} palavras.\n"
                . 'Tema: ' . (string) ($briefing['title'] ?? '') . "\n"
                . "Problema orientador: {$problem}\n"
                . "Objectivo geral orientador: {$generalObjective}\n"
                . 'Objectivos específicos orientadores: ' . implode('; ', $specificObjectives) . "\n"
                . 'Palavras-chave orientadoras: ' . implode(', ', $keywords) . "\n"
                . "Estilo de referências: {$referenceStyle}\n"
                . "Regras visuais (JSON): {$visualRulesJson}\n"
                . "Regras estruturais institucionais (JSON): {$structureRulesJson}\n"
                . "Folha de rosto institucional (JSON): {$frontPageJson}\n"
                . 'Fonte normativa: ' . (string) ($norm['source'] ?? 'none') . "\n"
                . 'Excerto normativo: ' . mb_substr(trim((string) ($norm['excerpt'] ?? 'não disponível')), 0, 1200) . "\n"
                . 'Notas normativas: ' . ($notes !== [] ? implode(' | ', array_map('strval', $notes)) : 'sem notas adicionais') . "\n"
                . "Política de template: {$templateResolution}\n\n"
                . "Guia específico da secção: {$sectionGuide}\n\n"
                . 'Instruções obrigatórias: entregar apenas o texto da secção, sem repetir o título; usar apenas texto corrido académico; não usar Markdown, hashtags, ###, ##, **, __, crases, bullets artificiais nem marcações de chat; não inventar autores, anos, referências bibliográficas, estatísticas ou resultados empíricos; quando faltarem dados específicos, redigir formulação académica geral e coerente sem denunciar ausência de dados; nunca escrever as expressões proibidas: revisão humana necessária, resumo indisponível, não foram fornecidos, não foram indicadas fontes, não foram disponibilizados.');
        }

        return $prompts;
    }

    private function nonEmpty(string $value, string $fallback): string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : $fallback;
    }

    private function normalizeList(mixed $items, array $fallback): array
    {
        if (!is_array($items)) {
            return $fallback;
        }

        $clean = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return $clean !== [] ? $clean : $fallback;
    }

    private function sectionGuidance(string $code, string $title, string $referenceStyle): string
    {
        return match (true) {
            str_contains($code, 'resumo') || str_contains(mb_strtolower($title), 'resumo')
                => 'Apresentar contexto, problema orientador, objectivo geral, abordagem metodológica e contribuição académica em síntese clara.',
            str_contains($code, 'introdu')
                => 'Contextualizar o tema, justificar relevância no cenário moçambicano e explicitar problema e objectivos com coerência.',
            str_contains($code, 'metod')
                => 'Descrever desenho metodológico, procedimentos analíticos, critérios éticos e limitações com linguagem técnica objectiva.',
            str_contains($code, 'resultado') || str_contains($code, 'discuss')
                => 'Discutir interpretações teóricas e implicações académicas com consistência argumentativa, sem fabricar dados empíricos.',
            str_contains($code, 'conclus')
                => 'Sintetizar contributos, responder ao problema e aos objectivos e propor recomendações académicas prudentes.',
            str_contains($code, 'refer')
                => "Listar referências em conformidade com {$referenceStyle}, mantendo neutralidade e plausibilidade académica.",
            default
                => 'Desenvolver conteúdo académico consistente, lógico e alinhado ao plano estrutural do trabalho.',
        };
    }
}
