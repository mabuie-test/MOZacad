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

        $globalContext = "[CONTEXTO_GLOBAL]\n"
            . "Tema: {$theme}\n"
            . "Problema de investigação: {$problem}\n"
            . "Objectivo geral: {$general}\n"
            . "Objectivos específicos: {$specific}\n"
            . "Palavras-chave do projecto: {$keywords}\n"
            . "Este contexto é permanente para todas as secções; não repetir mecanicamente problema/objectivos fora das secções em que isso seja contrato explícito.";

        $prompts[] = $globalContext;

        foreach ($blueprint as $i => $section) {
            $title = (string) ($section['title'] ?? 'Secção');
            $prev = (string) (($blueprint[$i-1]['title'] ?? 'nenhuma'));
            $next = (string) (($blueprint[$i+1]['title'] ?? 'nenhuma'));
            $contract = $this->sectionContract($title);
            $terms = $this->mandatoryTerms($theme, $title, $briefing);
            $prompts[] = "[INSTRUCAO_SECCAO]\n"
                . "Escreve apenas o corpo da secção '{$title}' em português académico de Moçambique.\n"
                . "Secção anterior: {$prev}; secção seguinte: {$next}.\n"
                . "Contrato da secção: {$contract}.\n"
                . "Para secções não introdutórias, não re-enunciar explicitamente problema/objectivos, excepto se necessário para coerência analítica pontual.\n"
                . "Cada parágrafo deve introduzir informação nova, verificável e específica do foco desta secção.\n"
                . "Instrução anti-fórmulas: não usar frases genéricas/placeholder como '{$forbidden}'. Se surgir formulação vaga, reformula com densidade conceptual (conceitos, relações causais, recorte temporal/espacial e evidência concreta).\n"
                . "Termos obrigatórios contextuais desta secção: {$terms}.\n"
                . "Proibido usar Markdown, referências inventadas ou texto meta-editorial.";
        }

        return $prompts;
    }

    private function sectionContract(string $title): string
    {
        $t = mb_strtolower($title);

        if (str_contains($t, 'introdu')) {
            return 'apresentar problema de investigação, objectivo geral, objectivos específicos e delimitação do estudo';
        }

        if (str_contains($t, 'metodolog')) {
            return 'explicitar desenho metodológico, procedimentos de recolha/tratamento de dados, critérios de validade e limitações';
        }

        if (str_contains($t, 'conclus')) {
            return 'sintetizar resultados centrais, implicações teóricas/práticas e recomendações consistentes com os achados';
        }

        return 'desenvolver apenas o propósito específico desta secção com progressão lógica e coerência argumentativa';
    }

    private function mandatoryTerms(string $theme, string $title, array $briefing): string
    {
        $t = mb_strtolower($theme . ' ' . $title);
        if (str_contains($t, 'moçambique') && str_contains($t, 'colon')) {
            return 'colonialismo português, administração colonial, estatuto do indigenato, missões religiosas, ensino rudimentar, língua portuguesa, currículo colonial, desigualdade de acesso, formação laboral, legado pós-independência';
        }

        $sectionSpecific = [];
        $keywords = (array) ($briefing['keywords'] ?? []);
        foreach ($keywords as $keyword) {
            $k = trim((string) $keyword);
            if ($k !== '' && (str_contains($t, mb_strtolower($k)) || mb_strlen($k) > 6)) {
                $sectionSpecific[] = $k;
            }
        }

        if ($sectionSpecific !== []) {
            return implode(', ', array_slice(array_unique($sectionSpecific), 0, 8));
        }

        if (str_contains($t, 'metodolog')) {
            return 'desenho de pesquisa, amostra/unidade de análise, técnicas de recolha de dados, critérios de validade';
        }

        if (str_contains($t, 'conclus')) {
            return 'síntese dos achados, implicações, limitações, recomendações';
        }

        return 'termos nucleares estritamente ligados ao tema e ao objectivo específico da secção';
    }
}
