<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

final class AIOrchestrationService
{
    private AIProviderInterface $provider;
    private ApplicationLoggerService $logger;

    public function __construct(?AIProviderInterface $provider = null)
    {
        $this->provider = $provider ?? (new AIProviderResolverService())->resolve();
        $this->logger = new ApplicationLoggerService();
    }

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
            $generated = trim($this->provider->generate($prompt, $context));
            if ($generated === '') {
                throw new \RuntimeException('Resposta vazia do provider IA.');
            }
            return $generated;
        } catch (Throwable $firstError) {
            $this->logger->error('ai.section.generate.primary_failed', [
                'section_code' => (string) ($context['section_code'] ?? ''),
                'error' => $firstError->getMessage(),
            ]);

            try {
                $fallbackPrompt = $prompt . "\n\nReescreve em formato mais objetivo e sem bullets.";
                $fallback = trim($this->provider->generate($fallbackPrompt, $context));
                if ($fallback === '') {
                    throw new \RuntimeException('Resposta vazia do provider IA no fallback.');
                }
                return $fallback;
            } catch (Throwable $fallbackError) {
                $this->logger->error('ai.section.generate.fallback_failed', [
                    'section_code' => (string) ($context['section_code'] ?? ''),
                    'error' => $fallbackError->getMessage(),
                ]);
                throw new \RuntimeException(
                    sprintf(
                        'Falha na geração IA da secção "%s" após fallback.',
                        (string) ($context['section_title'] ?? $context['section_code'] ?? 'desconhecida')
                    ),
                    0,
                    $fallbackError
                );
            }
        }
    }
}
