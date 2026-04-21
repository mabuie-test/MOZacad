<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

final class AIOrchestrationService
{
    public function __construct(private readonly AIProviderInterface $provider = new OpenAIProvider()) {}

    public function run(array $prompts, array $blueprint = []): array
    {
        $result = [];

        foreach ($prompts as $index => $prompt) {
            $title = (string) ($blueprint[$index]['title'] ?? ('Secção ' . ($index + 1)));
            $code = (string) ($blueprint[$index]['code'] ?? 'section_' . ($index + 1));

            $context = [
                'section_code' => $code,
                'section_title' => $title,
                'min_words' => (int) ($blueprint[$index]['min_words'] ?? 0),
                'max_words' => (int) ($blueprint[$index]['max_words'] ?? 0),
            ];

            $generated = $this->generateSection((string) $prompt, $context);

            $result[] = [
                'title' => $title,
                'code' => $code,
                'content' => $generated,
            ];
        }

        return $result;
    }

    private function generateSection(string $prompt, array $context): string
    {
        try {
            return trim($this->provider->generate($prompt, $context));
        } catch (Throwable) {
            $fallbackPrompt = $prompt . "\n\nReescreve em formato mais objetivo e sem bullets.";
            return trim($this->provider->generate($fallbackPrompt, $context));
        }
    }
}
