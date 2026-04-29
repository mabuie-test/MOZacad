<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;

final class AIProviderResolverService
{
    public function resolve(): AIProviderInterface
    {
        $config = Config::get('ai');
        $provider = strtolower(trim((string) ($config['provider']['default'] ?? 'openai')));
        $mode = strtolower(trim((string) ($config['provider']['mode'] ?? 'failover')));
        $failoverEnabled = (bool) ($config['provider']['failover']['enabled'] ?? true);

        if ($mode === 'single' || !$failoverEnabled) {
            return match ($provider) {
                'openai', '' => new OpenAIProvider(),
                'gemini' => new GeminiProvider(),
                default => throw new \RuntimeException(sprintf('AI_PROVIDER inválido: %s', $provider)),
            };
        }

        $chain = (array) ($config['provider']['failover']['chain'][$provider] ?? []);
        if (count($chain) < 2) {
            throw new \RuntimeException(sprintf('Cadeia de failover inválida para provider: %s', $provider));
        }

        return new FailoverAIProvider(
            $this->makeProvider((string) $chain[0]),
            $this->makeProvider((string) $chain[1])
        );
    }

    private function makeProvider(string $provider): AIProviderInterface
    {
        return match (strtolower(trim($provider))) {
            'openai', '' => new OpenAIProvider(),
            'gemini' => new GeminiProvider(),
            default => throw new \RuntimeException(sprintf('AI provider inválido: %s', $provider)),
        };
    }
}
