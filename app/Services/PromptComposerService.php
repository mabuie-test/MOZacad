<?php

declare(strict_types=1);

namespace App\Services;

final class PromptComposerService
{
    public function compose(array $blueprint, array $rules, array $briefing): array
    {
        $prompts = [];

        foreach ($blueprint as $section) {
            $template = <<<TXT
Escreve a secção académica em português de Moçambique.
Secção: %s
Tema: %s
Problema: %s
Objectivo geral: %s
Objectivos específicos: %s
Palavras-chave: %s
Limite de palavras: %d-%d
Estilo de referência: %s
Regras visuais relevantes: %s

Produz conteúdo natural, não repetitivo, com tom académico formal e verificável.
TXT;

            $prompts[] = trim(sprintf(
                $template,
                $section['title'],
                $briefing['title'] ?? '',
                $briefing['problem'] ?? 'Não informado',
                $briefing['generalObjective'] ?? 'Não informado',
                implode('; ', $briefing['specificObjectives'] ?? []),
                implode(', ', $briefing['keywords'] ?? []),
                (int) ($section['min_words'] ?? 250),
                (int) ($section['max_words'] ?? 800),
                $rules['referenceRules']['style'] ?? 'APA',
                json_encode($rules['visualRules'] ?? [], JSON_UNESCAPED_UNICODE)
            ));
        }

        return $prompts;
    }
}
