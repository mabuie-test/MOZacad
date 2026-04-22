<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class AIProviderResolverService
{
    public function resolve(): AIProviderInterface
    {
        $provider = strtolower(trim((string) Env::get('AI_PROVIDER', 'openai')));

        return match ($provider) {
            'openai', '' => new OpenAIProvider(),
            default => new OpenAIProvider(),
        };
    }
}

