<?php

declare(strict_types=1);

namespace App\Services;

final class MozPortugueseHumanizerService
{
    private array $replacements = [
        'você' => 'o estudante',
        'ônibus' => 'autocarro',
        'trem' => 'comboio',
        'fato' => 'facto',
        'objetivo' => 'objectivo',
    ];

    public function humanize(array $sections, string $profile = 'academic_humanized_pt_mz'): array
    {
        foreach ($sections as &$section) {
            $text = (string) ($section['content'] ?? '');
            $text = str_ireplace(array_keys($this->replacements), array_values($this->replacements), $text);
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            $text = preg_replace('/(\.\s+){2,}/', '. ', $text) ?? $text;
            $section['content'] = trim($text) . "\n\n[perfil_linguistico={$profile}]";
        }

        unset($section);

        return $sections;
    }
}
