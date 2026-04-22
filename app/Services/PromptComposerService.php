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

        $norm = is_array($rules['meta']['institution_norm'] ?? null) ? $rules['meta']['institution_norm'] : [];
        $normSource = (string) ($norm['source'] ?? 'none');
        $normExcerpt = trim((string) ($norm['excerpt'] ?? ''));
        $normLine = $normExcerpt !== ''
            ? '- Excerto normativo institucional: ' . mb_substr($normExcerpt, 0, 1200)
            : '- Excerto normativo institucional: não disponível';

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
- Fonte normativa institucional: {$normSource}
{$normLine}

Instruções obrigatórias:
1) Texto académico formal, coeso e verificável.
2) Sem invenção de dados, sem plágio, sem listas artificiais.
3) Inclui citações no estilo {$referenceStyle} quando apropriado.
4) Mantém foco na secção {$title} e respeita rigorosamente a base normativa institucional.
TXT);
        }

        return $prompts;
    }
}
