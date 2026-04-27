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

        $problem = $this->nonEmpty((string) ($briefing['problem'] ?? ''), 'Analisar o tema proposto no contexto académico moçambicano, delimitando causas, contexto e relevância científica.');
        $generalObjective = $this->nonEmpty((string) ($briefing['generalObjective'] ?? ''), 'Desenvolver uma abordagem académica coerente e rigorosa sobre o tema, com fundamentação teórica adequada.');
        $specificObjectives = $this->normalizeList($briefing['specificObjectives'] ?? [], [
            'Descrever os principais conceitos e enquadramentos teóricos do tema.',
            'Apresentar uma metodologia compatível com os objectivos da investigação.',
            'Discutir implicações práticas e académicas com linguagem formal.',
        ]);
        $keywords = $this->normalizeList($briefing['keywords'] ?? [], ['investigação académica', 'metodologia científica', 'contexto moçambicano']);

        foreach ($blueprint as $section) {
            $title = (string) ($section['title'] ?? 'Secção');
            $code = mb_strtolower((string) ($section['code'] ?? 'section'));
            $minWords = (int) ($section['min_words'] ?? 250);
            $maxWords = (int) ($section['max_words'] ?? 800);

            $sectionGuide = $this->sectionGuidance($code, $title, $referenceStyle);

            $prompts[] = trim("Escreve a secção académica em português de Moçambique, com fluidez humana e formalidade universitária.\n\n"
                . "Código: {$code}; Título: {$title}; Limite: {$minWords}-{$maxWords} palavras.\n"
                . "Tema: " . (string) ($briefing['title'] ?? '') . "\n"
                . "Problema orientador: {$problem}\n"
                . "Objectivo geral: {$generalObjective}\n"
                . "Objectivos específicos: " . implode('; ', $specificObjectives) . "\n"
                . "Palavras-chave orientadoras: " . implode(', ', $keywords) . "\n"
                . "Estilo de referências: {$referenceStyle}\n"
                . "Regras visuais (JSON): {$visualRulesJson}\n"
                . "Regras estruturais institucionais (JSON): {$structureRulesJson}\n"
                . "Folha de rosto institucional (JSON): {$frontPageJson}\n"
                . "Fonte normativa: " . (string) ($norm['source'] ?? 'none') . "\n"
                . "Excerto normativo: " . mb_substr(trim((string) ($norm['excerpt'] ?? 'não disponível')), 0, 1200) . "\n"
                . "Notas normativas: " . ($notes !== [] ? implode(' | ', array_map('strval', $notes)) : 'sem notas adicionais') . "\n"
                . "Política de template: {$templateResolution}\n\n"
                . "Guia específico da secção: {$sectionGuide}\n\n"
                . "Instruções obrigatórias: usar apenas texto corrido académico; não usar Markdown, hashtags, bullets artificiais, formatação de chat, crases ou asteriscos; não inventar autores, anos, referências ou resultados empíricos; quando faltarem dados, redigir formulações académicas gerais sem declarar ausência de dados; não escrever as expressões proibidas: revisão humana necessária, resumo indisponível, não foram fornecidos, não foram indicadas fontes, não foram disponibilizados.");
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
                => 'Apresentar contexto, objectivo, abordagem metodológica, principais achados esperados e contribuição académica em síntese clara.',
            str_contains($code, 'introdu')
                => 'Contextualizar o tema, relevância no cenário moçambicano, problema de investigação e objectivos alinhados.',
            str_contains($code, 'metod')
                => 'Descrever desenho metodológico, estratégia de análise, critérios éticos e limitações de forma objectiva e técnica.',
            str_contains($code, 'resultado') || str_contains($code, 'discuss')
                => 'Discutir implicações analíticas com coerência argumentativa e consistência teórica, sem fabricar dados empíricos.',
            str_contains($code, 'conclus')
                => 'Sintetizar contributos, responder ao problema e aos objectivos, e indicar recomendações académicas prudentes.',
            str_contains($code, 'refer')
                => "Listar referências em conformidade com {$referenceStyle}, mantendo apenas entradas plausíveis e academicamente neutras.",
            default
                => 'Desenvolver conteúdo académico consistente, lógico e alinhado ao plano estrutural do trabalho.',
        };
    }
}
