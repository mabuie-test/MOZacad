<?php

declare(strict_types=1);

namespace App\Services;

final class PromptComposerService
{
    public function compose(array $blueprint, array $rules, array $briefing): array
    {
        $prompts = [];
        $theme = (string) ($briefing['title'] ?? '');
        $problem = (string) ($briefing['problem'] ?? '');
        $general = (string) ($briefing['generalObjective'] ?? '');
        $specific = implode('; ', (array) ($briefing['specificObjectives'] ?? []));
        $keywords = implode(', ', (array) ($briefing['keywords'] ?? []));
        $forbidden = 'No contexto do tema|O estudo apresenta síntese académica|A discussão académica evidencia|A interpretação privilegia|Conclui-se que o desenvolvimento do tema|Referências organizadas conforme';

        foreach ($blueprint as $i => $section) {
            $title = (string) ($section['title'] ?? 'Secção');
            $prev = (string) (($blueprint[$i-1]['title'] ?? 'nenhuma'));
            $next = (string) (($blueprint[$i+1]['title'] ?? 'nenhuma'));
            $terms = $this->mandatoryTerms($theme, $title);
            $prompts[] = "Escreve apenas o corpo da secção '{$title}' em português académico de Moçambique.\n"
                . "Tema: {$theme}\nProblema: {$problem}\nObjectivo geral: {$general}\nObjectivos específicos: {$specific}\nPalavras-chave: {$keywords}\n"
                . "Secção anterior: {$prev}; secção seguinte: {$next}.\n"
                . "Cada parágrafo deve introduzir informação nova e específica. Não repetir problema/objectivos em toda a secção.\n"
                . "Termos obrigatórios quando pertinentes: {$terms}.\n"
                . "Proibido usar frases genéricas/placeholder ({$forbidden}), Markdown, referências inventadas ou texto meta-editorial.";
        }

        return $prompts;
    }

    private function mandatoryTerms(string $theme, string $title): string
    {
        $t = mb_strtolower($theme . ' ' . $title);
        if (str_contains($t, 'moçambique') && str_contains($t, 'colon')) {
            return 'colonialismo português, administração colonial, estatuto do indigenato, missões religiosas, ensino rudimentar, língua portuguesa, currículo colonial, desigualdade de acesso, formação laboral, legado pós-independência';
        }
        return 'conceitos centrais da disciplina, recorte temporal e espacial, evidências teóricas';
    }
}
