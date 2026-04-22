<?php

declare(strict_types=1);

namespace App\Services;

final class CitationFormatterService
{
    public function format(array $sections, string $style = 'APA'): array
    {
        $style = strtoupper(trim($style)) !== '' ? strtoupper(trim($style)) : 'APA';
        $extracted = $this->extractCitationSignals($sections);
        $references = $this->buildProvisionalReferences($extracted, $style);

        foreach ($sections as $index => &$section) {
            $text = trim((string) ($section['content'] ?? ''));
            if ($text === '') {
                continue;
            }

            $section['content'] = $text;
            $section['citation_style'] = $style;
            $section['section_number'] = $index + 1;
        }
        unset($section);

        $sections[] = [
            'title' => 'Referências',
            'code' => 'references',
            'content' => implode("\n", array_map(static fn (array $r): string => $r['formatted'], $references)),
            'citation_style' => $style,
            'requires_manual_completion' => in_array(true, array_column($references, 'requires_manual_completion'), true),
            'reference_entries' => $references,
        ];

        return $sections;
    }

    private function extractCitationSignals(array $sections): array
    {
        $signals = [];

        foreach ($sections as $section) {
            $text = (string) ($section['content'] ?? '');
            if ($text === '') {
                continue;
            }

            preg_match_all('/\(([A-Za-zÀ-ÖØ-öø-ÿ\-\s.&]+),\s*(\d{4}[a-z]?)\)/u', $text, $authorYear, PREG_SET_ORDER);
            foreach ($authorYear as $match) {
                $signals[] = ['type' => 'author_year', 'author' => trim($match[1]), 'year' => trim($match[2])];
            }

            preg_match_all('/\bhttps?:\/\/[^\s)]+/iu', $text, $urls);
            foreach (($urls[0] ?? []) as $url) {
                $signals[] = ['type' => 'url', 'value' => trim($url)];
            }

            preg_match_all('/\b(?:doi:\s*|10\.\d{4,9}\/[\w.()\-;\/:]+)\b/iu', $text, $dois);
            foreach (($dois[0] ?? []) as $doi) {
                $clean = preg_replace('/^doi:\s*/i', '', trim($doi)) ?? trim($doi);
                $signals[] = ['type' => 'doi', 'value' => $clean];
            }
        }

        return $signals;
    }

    private function buildProvisionalReferences(array $signals, string $style): array
    {
        $references = [];
        $seen = [];

        foreach ($signals as $signal) {
            $key = md5(json_encode($signal));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if ($signal['type'] === 'author_year') {
                $formatted = sprintf('%s. (%s). [Referência incompleta - completar manualmente].', $this->normalizeAuthor((string) $signal['author']), $signal['year']);
                $references[] = ['formatted' => $formatted, 'style' => $style, 'requires_manual_completion' => true];
                continue;
            }

            if ($signal['type'] === 'doi') {
                $references[] = ['formatted' => 'DOI: ' . (string) $signal['value'], 'style' => $style, 'requires_manual_completion' => true];
                continue;
            }

            if ($signal['type'] === 'url') {
                $references[] = ['formatted' => 'Fonte online: ' . (string) $signal['value'], 'style' => $style, 'requires_manual_completion' => true];
            }
        }

        if ($references === []) {
            $references[] = ['formatted' => 'Nenhuma referência detectada automaticamente. Completar manualmente.', 'style' => $style, 'requires_manual_completion' => true];
        }

        return $references;
    }

    private function normalizeAuthor(string $author): string
    {
        $author = preg_replace('/\s+/', ' ', trim($author)) ?? trim($author);
        return mb_convert_case($author, MB_CASE_TITLE, 'UTF-8');
    }
}
