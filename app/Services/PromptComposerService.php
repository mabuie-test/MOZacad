<?php

declare(strict_types=1);

namespace App\Services;

final class PromptComposerService
{
    public function compose(array $blueprint, array $rules, array $briefing): array
    {
        $prompts = [];
        foreach ($blueprint as $section) {
            $prompts[] = sprintf(
                'Escreva a secção "%s" com tom académico pt_MZ, tema "%s", respeitando regras: %s',
                $section['title'],
                $briefing['title'] ?? '',
                json_encode($rules, JSON_UNESCAPED_UNICODE)
            );
        }
        return $prompts;
    }
}
