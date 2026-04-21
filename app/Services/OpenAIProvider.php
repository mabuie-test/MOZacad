<?php

declare(strict_types=1);

namespace App\Services;

final class OpenAIProvider implements AIProviderInterface
{
    public function generate(string $prompt): string
    {
        return "[Conteúdo assistido] " . $prompt;
    }

    public function refine(string $text, array $rules = []): string
    {
        return $text . "\n\n[Refinado segundo regras académicas]";
    }
}
