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

        foreach ($blueprint as $section) {
            $title = (string) ($section['title'] ?? 'Secção');
            $code = (string) ($section['code'] ?? 'section');
            $minWords = (int) ($section['min_words'] ?? 250);
            $maxWords = (int) ($section['max_words'] ?? 800);

            $prompts[] = trim("Escreve a secção académica em português de Moçambique.\n\n"
                . "Código: {$code}; Título: {$title}; Limite: {$minWords}-{$maxWords} palavras.\n"
                . "Tema: " . (string) ($briefing['title'] ?? '') . "\n"
                . "Problema: " . (string) ($briefing['problem'] ?? '') . "\n"
                . "Objectivo geral: " . (string) ($briefing['generalObjective'] ?? '') . "\n"
                . "Objectivos específicos: " . implode('; ', $briefing['specificObjectives'] ?? []) . "\n"
                . "Palavras-chave: " . implode(', ', $briefing['keywords'] ?? []) . "\n"
                . "Estilo de referências: {$referenceStyle}\n"
                . "Regras visuais (JSON): {$visualRulesJson}\n"
                . "Regras estruturais institucionais (JSON): {$structureRulesJson}\n"
                . "Folha de rosto institucional (JSON): {$frontPageJson}\n"
                . "Fonte normativa: " . (string) ($norm['source'] ?? 'none') . "\n"
                . "Excerto normativo: " . mb_substr(trim((string) ($norm['excerpt'] ?? 'não disponível')), 0, 1200) . "\n"
                . "Notas normativas: " . ($notes !== [] ? implode(' | ', array_map('strval', $notes)) : 'sem notas adicionais') . "\n"
                . "Política de template: {$templateResolution}\n\n"
                . "Instruções: manter rigor académico; não inventar bibliografia; quando faltar dado factual, sinalizar necessidade de revisão humana no texto.");
        }

        return $prompts;
    }
}
