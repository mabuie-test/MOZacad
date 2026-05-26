<?php
declare(strict_types=1);
namespace App\Services;

use App\DTOs\ReferenceEntryDTO;

final class CitationFormatterService
{
    public function __construct(
        private readonly BibliographicSignalParserService $signals = new BibliographicSignalParserService()
    ) {}

    public function format(array $sections, string $style = 'APA'): array
    {
        $style = strtoupper(trim($style)) !== '' ? strtoupper(trim($style)) : 'APA';

        $detectedSignals  = $this->signals->parse($sections);
        $sources          = $this->collectSources($detectedSignals);
        $enrichedSources  = array_map(fn (array $s): array => $this->enrichSource($s), $sources);
        $references       = $this->buildReferences($enrichedSources, $style);

        foreach ($sections as $index => &$section) {
            $text = trim((string) ($section['content'] ?? ''));
            if ($text === '') continue;
            $section['content']        = $text;
            $section['citation_style'] = $style;
            $section['section_number'] = $index + 1;
        }
        unset($section);

        $referenceArrays = array_map(static fn (ReferenceEntryDTO $e): array => $e->toArray(), $references);
        $hasIncomplete   = in_array(true, array_column($referenceArrays, 'requires_manual_completion'), true);

        // ── Formata o bloco de referências ──────────────────────────────
        $refLines = [];
        foreach ($references as $ref) {
            if ($ref->requires_manual_completion) {
                // Entrada incompleta mas com dados parciais — mostra o que há
                $partial = $this->buildPartialEntry($ref->enriched_source ?? [], $style);
                $refLines[] = $partial !== '' ? $partial : '[Referência incompleta — preencher dados em falta]';
            } else {
                $refLines[] = $ref->formatted;
            }
        }

        // Se nenhum sinal bibliográfico foi detectado gera aviso em vez de entrada vazia
        if ($detectedSignals === [] || ($sources === [] && $detectedSignals !== [])) {
            $refLines = [
                '[ATENÇÃO] Nenhuma citação no formato (' . ($style === 'ABNT' ? 'AUTOR, Ano' : 'Autor, Ano') . ') foi detectada no texto. '
                . 'Revise o documento e adicione as citações correctas antes de entregar.',
            ];
        }

        $sections[] = [
            'title'                      => 'Referências',
            'code'                       => 'references',
            'content'                    => implode("\n", $refLines),
            'citation_style'             => $style,
            'signals_detected'           => $detectedSignals,
            'collected_sources'          => $enrichedSources,
            'requires_manual_completion' => $hasIncomplete || $detectedSignals === [],
            'qa_checklist'               => [
                'referencias_completas'          => !$hasIncomplete && $detectedSignals !== [],
                'referencias_completas_blocking' => $hasIncomplete || $detectedSignals === [],
                'signals_count'                  => count($detectedSignals),
                'sources_complete'               => count(array_filter($enrichedSources, static fn ($s) => (bool) ($s['is_complete'] ?? false))),
                'sources_incomplete'             => count(array_filter($enrichedSources, static fn ($s) => !(bool) ($s['is_complete'] ?? false))),
            ],
            'reference_entries' => $referenceArrays,
        ];

        return $sections;
    }

    // ── Recolha e enriquecimento de fontes ───────────────────────────────

    private function collectSources(array $signals): array
    {
        $sources = [];
        $seen    = [];

        foreach ($signals as $signal) {
            $type = (string) ($signal['type'] ?? 'unknown');
            $key  = md5(json_encode($signal, JSON_UNESCAPED_UNICODE));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $source = [
                'source_type'        => $this->inferSourceType($type),
                'author'             => null,
                'year'               => null,
                'title'              => null,
                'publisher_or_venue' => null,
                'url_or_doi'         => null,
                'accessed_at'        => null,
                'raw_signal'         => $signal,
            ];

            if ($type === 'author_year') {
                $source['author'] = $this->normalizeAuthor((string) ($signal['author'] ?? ''));
                $source['year']   = trim((string) ($signal['year'] ?? ''));
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

        // Sem sinais → retorna array vazio (tratado no format())
        return $sources;
    }

    private function enrichSource(array $source): array
    {
        $raw = is_array($source['raw_signal'] ?? null) ? $source['raw_signal'] : [];

        $source['title']              = $this->firstNonEmpty((string) ($source['title'] ?? ''), (string) ($raw['title'] ?? ''), (string) ($raw['work'] ?? ''), (string) ($raw['source'] ?? ''));
        $source['publisher_or_venue'] = $this->firstNonEmpty((string) ($source['publisher_or_venue'] ?? ''), (string) ($raw['venue'] ?? ''), (string) ($raw['publisher'] ?? ''), (string) ($raw['journal'] ?? ''));
        $source['author']             = $this->firstNonEmpty((string) ($source['author'] ?? ''), (string) ($raw['author'] ?? ''), (string) ($raw['organization'] ?? ''));

        if (($source['author'] ?? '') !== '') {
            $source['author'] = $this->normalizeAuthor((string) $source['author']);
        }

        $source['year']       = $this->normalizeYear($this->firstNonEmpty((string) ($source['year'] ?? ''), (string) ($raw['year'] ?? ''), (string) ($raw['published_at'] ?? '')));
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
            $formatted  = $isComplete
                ? $this->formatByStyleAndType($source, $style)
                : $this->buildPartialEntry($source, $style);

            $items[] = new ReferenceEntryDTO(
                (string) ($source['raw_signal']['type'] ?? 'unknown'),
                json_encode($source['raw_signal'] ?? [], JSON_UNESCAPED_UNICODE) ?: '',
                $formatted,
                !$isComplete,
                $isComplete ? 'approved' : 'incomplete',
                $source,
            );
        }
        return $items;
    }

    // ── Formatação por norma ────────────────────────────────────────────

    private function formatByStyleAndType(array $source, string $style): string
    {
        return match ($style) {
            'ABNT'       => $this->formatAbnt($source),
            'VANCOUVER'  => $this->formatVancouver($source),
            default      => $this->formatApa($source),
        };
    }

    private function formatApa(array $source): string
    {
        $author = (string) ($source['author'] ?? '');
        $year   = (string) ($source['year']   ?? '');
        $title  = (string) ($source['title']  ?? '');
        $venue  = (string) ($source['publisher_or_venue'] ?? '');
        $url    = (string) ($source['url_or_doi'] ?? '');
        $access = (string) ($source['accessed_at'] ?? '');

        $base = match ((string) ($source['source_type'] ?? 'report')) {
            'book'    => "{$author} ({$year}). {$title}. {$venue}.",
            'article' => "{$author} ({$year}). {$title}. {$venue}.",
            'website' => "{$author} ({$year}). {$title}. {$venue}.",
            default   => "{$author} ({$year}). {$title}. {$venue}.",
        };

        if ($url !== '') $base .= " {$url}";
        if ($access !== '' && ($source['source_type'] ?? '') === 'website') {
            $base .= " Acesso em: {$access}.";
        }

        return rtrim($base, '.') . '.';
    }

    private function formatAbnt(array $source): string
    {
        $author = mb_strtoupper((string) ($source['author'] ?? ''));
        $year   = (string) ($source['year']   ?? '');
        $title  = (string) ($source['title']  ?? '');
        $venue  = (string) ($source['publisher_or_venue'] ?? '');
        $url    = (string) ($source['url_or_doi'] ?? '');
        $access = (string) ($source['accessed_at'] ?? '');

        $base = "{$author}. {$title}. {$venue}, {$year}.";
        if ($url    !== '') $base .= " Disponível em: {$url}.";
        if ($access !== '') $base .= " Acesso em: " . date('d/m/Y', strtotime($access)) . ".";
        return $base;
    }

    private function formatVancouver(array $source): string
    {
        $author = (string) ($source['author'] ?? '');
        $year   = (string) ($source['year']   ?? '');
        $title  = (string) ($source['title']  ?? '');
        $venue  = (string) ($source['publisher_or_venue'] ?? '');
        $url    = (string) ($source['url_or_doi'] ?? '');
        $base   = "{$author}. {$title}. {$venue}; {$year}.";
        if ($url !== '') $base .= " Disponível em: {$url}.";
        return $base;
    }

    /** Entrada parcial com os dados disponíveis + marcador de revisão */
    private function buildPartialEntry(array $source, string $style): string
    {
        $author = trim((string) ($source['author'] ?? ''));
        $year   = trim((string) ($source['year']   ?? ''));
        $title  = trim((string) ($source['title']  ?? ''));
        $venue  = trim((string) ($source['publisher_or_venue'] ?? ''));
        $url    = trim((string) ($source['url_or_doi'] ?? ''));

        $parts = array_filter([
            $author !== '' ? $author                          : '[AUTOR — preencher]',
            $year   !== '' ? "({$year})"                     : '([ANO — preencher])',
            $title  !== '' ? $title                          : '[TÍTULO — preencher]',
            $venue  !== '' ? $venue                          : '[EDITORA/REVISTA — preencher]',
            $url    !== '' ? $url                            : '',
        ]);

        return implode('. ', $parts) . '. [VERIFICAR — referência incompleta]';
    }

    // ── Utilitários ──────────────────────────────────────────────────────

    private function isSourceComplete(array $source): bool
    {
        foreach (['author', 'year', 'title', 'publisher_or_venue'] as $f) {
            if (!is_string($source[$f] ?? null) || trim((string) $source[$f]) === '') return false;
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
            'url'  => 'website',
            'doi'  => 'article',
            default => 'report',
        };
    }

    private function firstNonEmpty(string ...$values): ?string
    {
        foreach ($values as $v) { $t = trim($v); if ($t !== '') return $t; }
        return null;
    }

    private function normalizeYear(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        if (preg_match('/\b(19|20)\d{2}\b/', $value, $m) === 1) return $m[0];
        return trim($value);
    }

    private function normalizeLocator(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        $value = trim($value);
        if (str_starts_with(strtolower($value), '10.')) return 'https://doi.org/' . $value;
        return $value;
    }

    private function normalizeAuthor(string $author): string
    {
        return mb_convert_case(
            trim(preg_replace('/\s+/', ' ', $author) ?? $author),
            MB_CASE_TITLE,
            'UTF-8'
        );
    }
}