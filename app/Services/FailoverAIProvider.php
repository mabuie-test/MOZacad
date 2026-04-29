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
            'generate',
            $context
        );
    }

    public function refine(string $text, array $rules = []): string
    {
        return $this->withFallback(
            fn (AIProviderInterface $provider): string => $provider->refine($text, $rules),
            'refine',
            $rules
        );
    }

    public function humanize(string $text, string $profile = 'academic_humanized_pt_mz'): string
    {
        return $this->withFallback(
            fn (AIProviderInterface $provider): string => $provider->humanize($text, $profile),
            'humanize',
            ['profile' => $profile]
        );
    }

    public function generateStructured(string $prompt, array $schema): array
    {
        return $this->withFallback(
            fn (AIProviderInterface $provider): array => $provider->generateStructured($prompt, $schema),
            'generate_structured',
            $schema
        );
    }

    private function withFallback(callable $operation, string $operationName, array $metadata = []): mixed
    {
        $section = $this->extractSection($metadata);
        $primaryProvider = $this->providerName($this->primary);
        $secondaryProvider = $this->providerName($this->secondary);

        try {
            $result = $operation($this->primary);
            $this->logger->info('ai.provider.used', [
                'operation' => $operationName,
                'section' => $section,
                'provider' => $primaryProvider,
                'fallback_used' => false,
            ]);
            return $result;
        } catch (Throwable $primaryError) {
            $this->logger->error('ai.provider.failover.primary_failed', [
                'operation' => $operationName,
                'section' => $section,
                'provider' => $primaryProvider,
                'error' => $primaryError->getMessage(),
            ]);

            try {
                $result = $operation($this->secondary);
                $this->logger->info('ai.provider.used', [
                    'operation' => $operationName,
                    'section' => $section,
                    'provider' => $secondaryProvider,
                    'fallback_used' => true,
                    'primary_provider' => $primaryProvider,
                ]);
                $this->logger->alert('ai.provider.failover.used', [
                    'operation' => $operationName,
                    'section' => $section,
                    'from_provider' => $primaryProvider,
                    'to_provider' => $secondaryProvider,
                ]);
                return $result;
            } catch (Throwable $secondaryError) {
                $this->logger->error('ai.provider.failover.secondary_failed', [
                    'operation' => $operationName,
                    'section' => $section,
                    'provider' => $secondaryProvider,
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

    private function providerName(AIProviderInterface $provider): string
    {
        return match (true) {
            $provider instanceof OpenAIProvider => 'openai',
            $provider instanceof GeminiProvider => 'gemini',
            default => get_debug_type($provider),
        };
    }

    private function extractSection(array $metadata): string
    {
        $keys = ['section', 'section_title', 'title', 'section_code', 'code'];
        foreach ($keys as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'unknown';
    }
}
