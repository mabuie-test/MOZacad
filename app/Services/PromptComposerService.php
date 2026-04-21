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

        foreach ($blueprint as $section) {
            $title = (string) ($section['title'] ?? 'Secção');
            $code = (string) ($section['code'] ?? 'section');
            $minWords = (int) ($section['min_words'] ?? 250);
            $maxWords = (int) ($section['max_words'] ?? 800);
            $keywords = implode(', ', $briefing['keywords'] ?? []);
            $specificObjectives = implode('; ', $briefing['specificObjectives'] ?? []);

            $prompts[] = trim(<<<TXT
Escreve a secção académica em português de Moçambique.

Dados da secção:
- Código: {$code}
- Título: {$title}
- Limite: {$minWords} a {$maxWords} palavras

Contexto do pedido:
- Tema: {$briefing['title']}
- Problema: {$briefing['problem']}
- Objectivo geral: {$briefing['generalObjective']}
- Objectivos específicos: {$specificObjectives}
- Palavras-chave: {$keywords}

Regras institucionais:
- Estilo de referências: {$referenceStyle}
- Regras visuais (JSON): {$visualRulesJson}

Instruções obrigatórias:
1) Texto académico formal, coeso e verificável.
2) Sem invenção de dados, sem plágio, sem listas artificiais.
3) Inclui citações no estilo {$referenceStyle} quando apropriado.
4) Mantém foco na secção {$title}.
TXT);
        }

        return $prompts;
    }
}
