<?php

declare(strict_types=1);

namespace App\Services;

final class AIOrchestrationService
{
    public function __construct(private readonly AIProviderInterface $provider = new OpenAIProvider()) {}

    public function run(array $prompts): array
    {
        $result = [];
        foreach ($prompts as $prompt) {
            $result[] = $this->provider->generate($prompt);
        }
        return $result;
    }
}
