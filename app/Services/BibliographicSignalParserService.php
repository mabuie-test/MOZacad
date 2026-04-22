<?php

declare(strict_types=1);

namespace App\Services;

final class BibliographicSignalParserService
{
    public function parse(array $sections): array
    {
        $signals = [];
        foreach ($sections as $section) {
            $text = (string) ($section['content'] ?? '');
            if (trim($text) === '') {
                continue;
            }

            preg_match_all('/\(([A-Za-zÀ-ÖØ-öø-ÿ\-\s.&]+),\s*(\d{4}[a-z]?)\)/u', $text, $authorYear, PREG_SET_ORDER);
            foreach ($authorYear as $m) {
                $signals[] = ['type' => 'author_year', 'author' => trim($m[1]), 'year' => trim($m[2])];
            }

            preg_match_all('/\b(?:doi:\s*)?(10\.\d{4,9}\/[\w.()\-;\/:]+)\b/iu', $text, $dois);
            foreach (($dois[0] ?? []) as $doi) {
                $signals[] = ['type' => 'doi', 'value' => preg_replace('/^doi:\s*/i', '', trim($doi)) ?: trim($doi)];
            }

            preg_match_all('/\bhttps?:\/\/[^\s)]+/iu', $text, $urls);
            foreach (($urls[0] ?? []) as $url) {
                $signals[] = ['type' => 'url', 'value' => trim($url)];
            }

            preg_match_all('/\bISBN(?:-1[03])?:?\s*([0-9\-Xx]{10,17})\b/u', $text, $isbns, PREG_SET_ORDER);
            foreach ($isbns as $isbn) {
                $signals[] = ['type' => 'isbn', 'value' => strtoupper(str_replace('-', '', trim((string) ($isbn[1] ?? ''))))];
            }
        }

        return $signals;
    }
}
