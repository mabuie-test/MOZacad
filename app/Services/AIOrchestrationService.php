<?php

declare(strict_types=1);

namespace App\Services;

final class AIOrchestrationService
{
    public function __construct(private readonly AIProviderInterface $provider = new OpenAIProvider()) {}

    public function run(array $prompts, array $blueprint = []): array
    {
        $result = [];

        foreach ($prompts as $index => $prompt) {
            $title = (string) ($blueprint[$index]['title'] ?? ('Secção ' . ($index + 1)));
            $generated = trim($this->provider->generate((string) $prompt));

            $result[] = [
                'title' => $title,
                'code' => (string) ($blueprint[$index]['code'] ?? ''),
                'content' => $generated,
            ];
        }

        return $result;
    }
}
