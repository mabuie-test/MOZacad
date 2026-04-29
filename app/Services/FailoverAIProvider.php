<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class FailoverAIProvider implements AIProviderInterface
{
    public function __construct(
        private AIProviderInterface $primary,
        private AIProviderInterface $secondary,
        private ?ApplicationLoggerService $logger = null
    ) {
        $this->logger ??= new ApplicationLoggerService();
    }

    public function generate(string $prompt, array $context = []): string
    {
        return $this->withFallback(
            fn (AIProviderInterface $provider): string => $provider->generate($prompt, $context),
            'generate'
        );
    }

    public function refine(string $text, array $rules = []): string
    {
        return $this->withFallback(
            fn (AIProviderInterface $provider): string => $provider->refine($text, $rules),
            'refine'
        );
    }

    public function humanize(string $text, string $profile = 'academic_humanized_pt_mz'): string
    {
        return $this->withFallback(
            fn (AIProviderInterface $provider): string => $provider->humanize($text, $profile),
            'humanize'
        );
    }

    public function generateStructured(string $prompt, array $schema): array
    {
        return $this->withFallback(
            fn (AIProviderInterface $provider): array => $provider->generateStructured($prompt, $schema),
            'generate_structured'
        );
    }

    private function withFallback(callable $operation, string $operationName): mixed
    {
        try {
            return $operation($this->primary);
        } catch (Throwable $primaryError) {
            $this->logger->error('ai.provider.failover.primary_failed', [
                'operation' => $operationName,
                'provider' => get_debug_type($this->primary),
                'error' => $primaryError->getMessage(),
            ]);

            try {
                return $operation($this->secondary);
            } catch (Throwable $secondaryError) {
                $this->logger->error('ai.provider.failover.secondary_failed', [
                    'operation' => $operationName,
                    'provider' => get_debug_type($this->secondary),
                    'error' => $secondaryError->getMessage(),
                ]);

                throw new RuntimeException(
                    sprintf('Falha de IA em cadeia (%s): primary=%s | secondary=%s', $operationName, $primaryError->getMessage(), $secondaryError->getMessage()),
                    0,
                    $secondaryError
                );
            }
        }
    }
}
