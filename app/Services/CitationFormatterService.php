<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ReferenceEntryDTO;

final class CitationFormatterService
{
    public function __construct(private readonly BibliographicSignalParserService $signals = new BibliographicSignalParserService()) {}

    public function format(array $sections, string $style = 'APA'): array
    {
        $style = strtoupper(trim($style)) !== '' ? strtoupper(trim($style)) : 'APA';
        $detectedSignals = $this->signals->parse($sections);
        $references = $this->buildReferences($detectedSignals, $style);

        foreach ($sections as $index => &$section) {
            $text = trim((string) ($section['content'] ?? ''));
            if ($text === '') continue;
            $section['content'] = $text;
            $section['citation_style'] = $style;
            $section['section_number'] = $index + 1;
        }
        unset($section);

        $referenceArrays = array_map(static fn (ReferenceEntryDTO $entry): array => $entry->toArray(), $references);
        $sections[] = [
            'title' => 'Referências',
            'code' => 'references',
            'content' => implode("\n", array_map(static fn (ReferenceEntryDTO $r): string => $r->formatted, $references)),
            'citation_style' => $style,
            'signals_detected' => $detectedSignals,
            'requires_manual_completion' => in_array(true, array_column($referenceArrays, 'requires_manual_completion'), true),
            'reference_entries' => $referenceArrays,
        ];

        return $sections;
    }

    /** @return array<int,ReferenceEntryDTO> */
    private function buildReferences(array $signals, string $style): array
    {
        $items = [];
        $seen = [];

        foreach ($signals as $signal) {
            $hash = md5(json_encode($signal, JSON_UNESCAPED_UNICODE));
            if (isset($seen[$hash])) continue;
            $seen[$hash] = true;

            $items[] = match ((string) ($signal['type'] ?? '')) {
                'author_year' => new ReferenceEntryDTO(
                    'author_year',
                    sprintf('%s|%s', (string) ($signal['author'] ?? ''), (string) ($signal['year'] ?? '')),
                    sprintf('%s. (%s). [Referência provisória incompleta — revisão manual obrigatória].', $this->normalizeAuthor((string) ($signal['author'] ?? 'Autor')), (string) ($signal['year'] ?? 's.d.')),
                    true,
                    'provisional'
                ),
                'doi' => new ReferenceEntryDTO('doi', (string) ($signal['value'] ?? ''), 'DOI: ' . (string) ($signal['value'] ?? ''), true, 'incomplete'),
                'url' => new ReferenceEntryDTO('url', (string) ($signal['value'] ?? ''), 'Fonte online: ' . (string) ($signal['value'] ?? ''), true, 'incomplete'),
                default => new ReferenceEntryDTO('unknown', json_encode($signal), '[Sinal bibliográfico não estruturado - revisão manual]', true, 'incomplete'),
            };
        }

        if ($items === []) {
            $items[] = new ReferenceEntryDTO('none', 'none', 'Nenhuma referência detectada automaticamente. Completar manualmente.', true, 'empty');
        }

        return $items;
    }

    private function normalizeAuthor(string $author): string
    {
        $author = preg_replace('/\s+/', ' ', trim($author)) ?? trim($author);
        return mb_convert_case($author, MB_CASE_TITLE, 'UTF-8');
    }
}
