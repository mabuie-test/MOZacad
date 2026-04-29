<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class AIProviderResolverService
{
    public function resolve(): AIProviderInterface
    {
        $provider = strtolower(trim((string) Env::get('AI_PROVIDER', 'openai')));
        $mode = strtolower(trim((string) Env::get('AI_PROVIDER_MODE', 'failover')));

        if ($mode === 'single') {
            return match ($provider) {
                'openai', '' => new OpenAIProvider(),
                'gemini' => new GeminiProvider(),
                default => throw new \RuntimeException(sprintf('AI_PROVIDER inválido: %s', $provider)),
            };
        }

        return match ($provider) {
            'openai', '' => new FailoverAIProvider(new OpenAIProvider(), new GeminiProvider()),
            'gemini' => new FailoverAIProvider(new GeminiProvider(), new OpenAIProvider()),
            default => throw new \RuntimeException(sprintf('AI_PROVIDER inválido: %s', $provider)),
        };
    }
}
