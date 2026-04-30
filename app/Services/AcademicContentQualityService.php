<?php

declare(strict_types=1);

namespace App\Services;

final class AcademicContentQualityService
{
    private array $forbidden = ['no contexto do tema', 'o estudo apresenta síntese académica', 'referências organizadas conforme'];

    public function validateSection(array $section, array $briefing, array $blueprint): array
    {
        $content = mb_strtolower(trim((string) ($section['content'] ?? '')));
        $issues = [];
        if (str_word_count($content) < 60) { $issues[] = 'too_short'; }
        if (str_contains($content, '#') || str_contains($content, '**')) { $issues[] = 'markdown_detected'; }
        foreach ($this->forbidden as $phrase) {
            if (str_contains($content, $phrase)) { $issues[] = 'forbidden_phrase'; break; }
        }

        return ['ok' => $issues === [], 'issues' => $issues];
    }

    public function validateDocument(array $sections, array $briefing, array $blueprint): array
    {
        $issues = [];
        foreach ($sections as $section) {
            $res = $this->validateSection($section, $briefing, $blueprint);
            if (!$res['ok']) { $issues[] = ['title' => $section['title'] ?? 'secção', 'issues' => $res['issues']]; }
        }

        return ['ok' => $issues === [], 'issues' => $issues];
    }
}
