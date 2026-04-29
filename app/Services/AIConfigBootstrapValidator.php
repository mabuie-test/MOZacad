<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use RuntimeException;

final class AIConfigBootstrapValidator
{
    public static function assertValid(): void
    {
        $config = Config::get('ai');
        $provider = strtolower(trim((string) ($config['provider']['default'] ?? 'openai')));
        $mode = strtolower(trim((string) ($config['provider']['mode'] ?? 'failover')));

        if (!in_array($provider, ['openai', 'gemini'], true)) {
            throw new RuntimeException(sprintf('Configuração inválida: ai.provider.default=%s', $provider));
        }

        if (!in_array($mode, ['single', 'failover'], true)) {
            throw new RuntimeException(sprintf('Configuração inválida: ai.provider.mode=%s', $mode));
        }

        self::assertProviderKey($config, $provider);

        $failoverEnabled = (bool) ($config['provider']['failover']['enabled'] ?? true);
        if ($mode === 'failover' && $failoverEnabled) {
            $chain = (array) ($config['provider']['failover']['chain'][$provider] ?? []);
            if (count($chain) < 2) {
                throw new RuntimeException(sprintf('Configuração crítica ausente: ai.provider.failover.chain.%s', $provider));
            }

            foreach (array_slice($chain, 0, 2) as $chainProvider) {
                self::assertProviderKey($config, (string) $chainProvider);
            }
        }
    }

    private static function assertProviderKey(array $config, string $provider): void
    {
        $key = trim((string) ($config[$provider]['api_key'] ?? ''));
        if ($key === '') {
            throw new RuntimeException(sprintf('Configuração crítica ausente: ai.%s.api_key', $provider));
        }
    }
}
