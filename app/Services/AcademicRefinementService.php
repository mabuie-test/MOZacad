<?php

declare(strict_types=1);

namespace App\Services;

final class AcademicRefinementService
{
    public function __construct(private readonly AIProviderInterface $provider = new OpenAIProvider()) {}

    public function refine(array $sections): array
    {
        $output = [];

        foreach ($sections as $section) {
            $text = trim((string) ($section['content'] ?? ''));
            if ($text === '') {
                continue;
            }

            $prompt = "Refina o texto académico abaixo para melhorar coesão, transições e rigor metodológico sem alterar o sentido.\n\n" . $text;
            $section['content'] = trim($this->provider->refine($prompt, ['goal' => 'coherence']));
            $output[] = $section;
        }

        return $output;
    }
}
