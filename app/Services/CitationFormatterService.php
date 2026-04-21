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

            preg_match_all('/\([A-Za-zÀ-ÖØ-öø-ÿ ,.&-]+,\s?\d{4}\)/u', $text, $authorYear);
            preg_match_all('/\b(?:doi:\s*|https?:\/\/)[^\s)]+/iu', $text, $linksAndDoi);

            foreach (($authorYear[0] ?? []) as $citation) {
                $references[] = $citation;
            }
            foreach (($linksAndDoi[0] ?? []) as $source) {
                $references[] = $source;
            }

            $section['content'] = $text;
            $section['citation_style'] = strtoupper($style);
            $section['section_number'] = $index + 1;
        }

        unset($section);

        $references = array_values(array_unique(array_filter(array_map('trim', $references))));

        $sections[] = [
            'title' => 'Referências',
            'code' => 'references',
            'content' => implode("\n", $references),
            'citation_style' => strtoupper($style),
            'requires_manual_completion' => $references === [],
        ];

        return $sections;
    }
}
