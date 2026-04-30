<?php

declare(strict_types=1);

namespace App\Services;

final class DynamicAcademicStructureService
{
    public function buildDynamicBlueprint(array $order, array $briefing, array $workType, array $baseBlueprint, array $rules): array
    {
        $title = mb_strtolower((string) ($briefing['title'] ?? $order['topic'] ?? ''));
        $pages = (int) ($order['target_pages'] ?? 5);

        if (str_contains($title, 'hist') && str_contains($title, 'moçambique') && str_contains($title, 'colon')) {
            $sections = [
                'Resumo',
                'Introdução',
                'Enquadramento histórico da educação colonial em Moçambique',
                'Estado colonial, missões religiosas e política assimilacionista',
                'Currículo, língua e formação para o trabalho',
                'Desigualdades de acesso e efeitos sociais',
                'Legados da educação colonial no pós-independência',
                'Conclusão',
                'Referências',
            ];
            if ($pages <= 3) { $sections = array_slice($sections, 0, 7); }
            return $this->mapSections($sections);
        }

        return $baseBlueprint;
    }

    private function mapSections(array $titles): array
    {
        $out = [];
        foreach ($titles as $idx => $title) {
            $out[] = ['code' => 'sec_' . ($idx + 1), 'title' => $title, 'min_words' => 140, 'max_words' => 420];
        }
        return $out;
    }
}
