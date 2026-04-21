<?php

declare(strict_types=1);

namespace App\Services;

final class CitationFormatterService
{
    public function format(array $sections, string $style = 'APA'): array
    {
        $references = [];

        foreach ($sections as $index => &$section) {
            $text = trim((string) ($section['content'] ?? ''));
            if ($text === '') {
                continue;
            }

            if (!preg_match('/\([A-Za-zÀ-ÖØ-öø-ÿ ,.&-]+,\s?\d{4}\)/u', $text)) {
                $section['content'] = rtrim($text, '.') . '.';
            }

            preg_match_all('/\[[^\]]+\]/u', $text, $foundInline);
            foreach (($foundInline[0] ?? []) as $item) {
                $references[] = $item;
            }

            $section['citation_style'] = strtoupper($style);
            $section['section_number'] = $index + 1;
        }

        unset($section);

        $references = array_values(array_unique($references));
        if ($references === []) {
            $references[] = 'Referências bibliográficas devem ser confirmadas e normalizadas na revisão final.';
        }

        $sections[] = [
            'title' => 'Referências',
            'code' => 'references',
            'content' => implode("\n", $references),
            'citation_style' => strtoupper($style),
        ];

        return $sections;
    }
}
