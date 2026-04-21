<?php

declare(strict_types=1);

namespace App\Services;

final class CitationFormatterService
{
    public function format(array $sections, string $style = 'APA'): array
    {
        $references = [];

        foreach ($sections as $index => &$section) {
            $citationTag = sprintf('(%s, %d)', date('Y'), $index + 1);
            $section['content'] = rtrim((string) $section['content']) . " {$citationTag}.";
            $references[] = sprintf('Fonte %d. Referência normalizada em %s.', $index + 1, strtoupper($style));
        }

        unset($section);

        $sections[] = [
            'title' => 'Referências',
            'code' => 'references',
            'content' => implode("\n", $references),
        ];

        return $sections;
    }
}
