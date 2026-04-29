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
        $sources = $this->collectSources($detectedSignals);
        $enrichedSources = array_map(fn (array $source): array => $this->enrichSource($source), $sources);
        $references = $this->buildReferences($enrichedSources, $style);

        foreach ($sections as $index => &$section) {
            $text = trim((string) ($section['content'] ?? ''));
            if ($text === '') continue;
            $section['content'] = $text;
            $section['citation_style'] = $style;
            $section['section_number'] = $index + 1;
        }
        unset($section);

        $referenceArrays = array_map(static fn (ReferenceEntryDTO $entry): array => $entry->toArray(), $references);
        $hasIncomplete = in_array(true, array_column($referenceArrays, 'requires_manual_completion'), true);

        $sections[] = [
            'title' => 'Referências',
            'code' => 'references',
            'content' => implode("\n", array_map(static fn (ReferenceEntryDTO $r): string => $r->formatted, $references)),
            'citation_style' => $style,
            'signals_detected' => $detectedSignals,
            'collected_sources' => $enrichedSources,
            'requires_manual_completion' => $hasIncomplete,
            'qa_checklist' => [
                'referencias_completas' => !$hasIncomplete,
                'referencias_completas_blocking' => $hasIncomplete,
            ],
            'reference_entries' => $referenceArrays,
        ];

        return $sections;
    }

    private function collectSources(array $signals): array
    {
        $sources = [];
        $seen = [];

        foreach ($signals as $signal) {
            $type = (string) ($signal['type'] ?? 'unknown');
            $key = md5(json_encode($signal, JSON_UNESCAPED_UNICODE));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $source = [
                'source_type' => $this->inferSourceType($type),
                'author' => null,
                'year' => null,
                'title' => null,
                'publisher_or_venue' => null,
                'url_or_doi' => null,
                'accessed_at' => null,
                'raw_signal' => $signal,
            ];

            if ($type === 'author_year') {
                $source['author'] = $this->normalizeAuthor((string) ($signal['author'] ?? ''));
                $source['year'] = trim((string) ($signal['year'] ?? ''));
            }
            if ($type === 'doi') {
                $source['url_or_doi'] = 'https://doi.org/' . trim((string) ($signal['value'] ?? ''));
            }
            if ($type === 'url') {
                $source['url_or_doi'] = trim((string) ($signal['value'] ?? ''));
                $source['accessed_at'] = date('Y-m-d');
            }

            $source['is_complete'] = $this->isSourceComplete($source);
            $sources[] = $source;
        }

        if ($sources === []) {
            $sources[] = [
                'source_type' => 'unknown',
                'author' => null,
                'year' => null,
                'title' => null,
                'publisher_or_venue' => null,
                'url_or_doi' => null,
                'accessed_at' => null,
                'raw_signal' => ['type' => 'none'],
                'is_complete' => false,
            ];
        }

        return $sources;
    }


    private function enrichSource(array $source): array
    {
        $raw = $source['raw_signal'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $source['title'] = $this->firstNonEmpty((string) ($source['title'] ?? ''), (string) ($raw['title'] ?? ''), (string) ($raw['work'] ?? ''), (string) ($raw['source'] ?? ''));
        $source['publisher_or_venue'] = $this->firstNonEmpty((string) ($source['publisher_or_venue'] ?? ''), (string) ($raw['venue'] ?? ''), (string) ($raw['publisher'] ?? ''), (string) ($raw['journal'] ?? ''));
        $source['author'] = $this->firstNonEmpty((string) ($source['author'] ?? ''), (string) ($raw['author'] ?? ''), (string) ($raw['organization'] ?? ''));
        if (($source['author'] ?? '') !== '') {
            $source['author'] = $this->normalizeAuthor((string) $source['author']);
        }
        $source['year'] = $this->normalizeYear($this->firstNonEmpty((string) ($source['year'] ?? ''), (string) ($raw['year'] ?? ''), (string) ($raw['published_at'] ?? '')));

        $source['url_or_doi'] = $this->normalizeLocator($this->firstNonEmpty((string) ($source['url_or_doi'] ?? ''), (string) ($raw['url'] ?? ''), (string) ($raw['doi'] ?? '')));

        if (($source['source_type'] ?? '') === 'website' && trim((string) ($source['accessed_at'] ?? '')) === '') {
            $source['accessed_at'] = date('Y-m-d');
        }

        $source['is_complete'] = $this->isSourceComplete($source);

        return $source;
    }

    /** @return array<int,ReferenceEntryDTO> */
    private function buildReferences(array $sources, string $style): array
    {
        $items = [];

        foreach ($sources as $source) {
            $isComplete = (bool) ($source['is_complete'] ?? false);
            $formatted = $isComplete
                ? $this->formatByStyleAndType($source, $style)
                : 'Referência incompleta — preencher autor, ano, título, veículo/editora e URL/DOI.';

            $items[] = new ReferenceEntryDTO(
                (string) ($source['raw_signal']['type'] ?? 'unknown'),
                json_encode($source['raw_signal'], JSON_UNESCAPED_UNICODE) ?: '',
                $formatted,
                !$isComplete,
                $isComplete ? 'approved' : 'incomplete',
                $source,
            );
        }

        return $items;
    }

    private function formatByStyleAndType(array $source, string $style): string
    {
        return match ($style) {
            'ABNT' => $this->formatAbnt($source),
            default => $this->formatApa($source),
        };
    }

    private function formatApa(array $source): string
    {
        $author = (string) $source['author'];
        $year = (string) $source['year'];
        $title = (string) $source['title'];
        $venue = (string) $source['publisher_or_venue'];
        $url = (string) $source['url_or_doi'];

        return match ((string) $source['source_type']) {
            'book' => "{$author}. ({$year}). {$title}. {$venue}. {$url}",
            'report' => "{$author}. ({$year}). {$title} ({$venue}). {$url}",
            'website' => "{$author}. ({$year}). {$title}. {$venue}. {$url}",
            default => "{$author}. ({$year}). {$title}. {$venue}. {$url}",
        };
    }

    private function formatAbnt(array $source): string
    {
        $author = mb_strtoupper((string) $source['author']);
        $year = (string) $source['year'];
        $title = (string) $source['title'];
        $venue = (string) $source['publisher_or_venue'];
        $url = (string) $source['url_or_doi'];
        $access = (string) ($source['accessed_at'] ?? '');

        $base = "{$author}. {$title}. {$venue}, {$year}. Disponível em: {$url}.";
        if ($access !== '') {
            return $base . " Acesso em: {$access}.";
        }

        return $base;
    }

    private function isSourceComplete(array $source): bool
    {
        $required = ['author', 'year', 'title', 'publisher_or_venue', 'url_or_doi'];
        foreach ($required as $field) {
            if (!is_string($source[$field] ?? null) || trim((string) $source[$field]) === '') {
                return false;
            }
        }

        if (($source['source_type'] ?? '') === 'website') {
            return is_string($source['accessed_at'] ?? null) && trim((string) $source['accessed_at']) !== '';
        }

        return true;
    }

    private function inferSourceType(string $signalType): string
    {
        return match ($signalType) {
            'isbn' => 'book',
            'url' => 'website',
            'doi' => 'article',
            default => 'report',
        };
    }


    private function firstNonEmpty(string ...$values): ?string
    {
        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function normalizeYear(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $value, $m) === 1) {
            return $m[0];
        }

        return trim($value);
    }

    private function normalizeLocator(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (str_starts_with(strtolower($value), '10.')) {
            return 'https://doi.org/' . $value;
        }

        return $value;
    }
    private function normalizeAuthor(string $author): string
    {
        $author = preg_replace('/\s+/', ' ', trim($author)) ?? trim($author);
        return mb_convert_case($author, MB_CASE_TITLE, 'UTF-8');
    }
}
