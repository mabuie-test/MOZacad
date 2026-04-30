<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use App\Helpers\Database;
use Throwable;

final class AIProviderPreflightService
{
    private const STATUS_OK = 'ok';
    private const STATUS_DEGRADED = 'degraded';
    private const STATUS_CRITICAL = 'critical';

    /** @return array<string,mixed> */
    public function currentStatus(bool $runCheck = false): array
    {
        if ($runCheck) {
            $this->runAndPersist();
        }

        $db = Database::connect();
        $latestStmt = $db->query('SELECT * FROM ai_preflight_checks ORDER BY id DESC LIMIT 1');
        $latest = $latestStmt !== false ? $latestStmt->fetch() : false;

        if (!is_array($latest)) {
            return [
                'status' => self::STATUS_CRITICAL,
                'last_check_at' => null,
                'providers' => [],
                'models' => [],
                'message' => 'Preflight ainda não executado.',
            ];
        }

        return [
            'status' => (string) ($latest['status'] ?? self::STATUS_CRITICAL),
            'last_check_at' => (string) ($latest['checked_at'] ?? null),
            'providers' => json_decode((string) ($latest['providers_json'] ?? '[]'), true) ?: [],
            'models' => json_decode((string) ($latest['models_json'] ?? '[]'), true) ?: [],
            'message' => (string) ($latest['summary'] ?? ''),
        ];
    }

    public function assertQueueAllowed(): void
    {
        $status = $this->currentStatus();
        if (($status['status'] ?? self::STATUS_CRITICAL) === self::STATUS_CRITICAL) {
            throw new \RuntimeException('Preflight IA crítico: enfileiramento bloqueado até normalização dos providers/modelos.');
        }
    }

    /** @return array<string,mixed> */
    public function runAndPersist(): array
    {
        $config = Config::get('ai');
        $primary = strtolower(trim((string) ($config['provider']['default'] ?? 'openai')));
        $failoverEnabled = (bool) ($config['provider']['failover']['enabled'] ?? true);
        $mode = strtolower(trim((string) ($config['provider']['mode'] ?? 'failover')));

        $providersToCheck = [$primary];
        if ($mode !== 'single' && $failoverEnabled) {
            $chain = (array) ($config['provider']['failover']['chain'][$primary] ?? []);
            $secondary = strtolower(trim((string) ($chain[1] ?? '')));
            if ($secondary !== '' && $secondary !== $primary) {
                $providersToCheck[] = $secondary;
            }
        }

        $providerResults = [];
        $modelResults = [];
        $failures = [];

        foreach ($providersToCheck as $providerName) {
            $provider = $this->makeProvider($providerName);
            $providerResult = $this->safeCheck(fn (): string => $provider->generate('Responda apenas: ok'));
            $providerResults[$providerName] = $providerResult;
            if (($providerResult['ok'] ?? false) !== true) {
                $failures[] = 'provider:' . $providerName;
                $this->registerFailureMetric($providerName, (string) $providerResult['error_type']);
            }

            foreach (['content', 'refinement', 'humanizer', 'structure'] as $task) {
                $key = $providerName . ':' . $task;
                $modelResults[$key] = $this->checkTask($provider, $task);
                if (($modelResults[$key]['ok'] ?? false) !== true) {
                    $failures[] = 'model:' . $key;
                    $this->registerFailureMetric($providerName, (string) $modelResults[$key]['error_type']);
                }
            }
        }

        $status = self::STATUS_OK;
        if ($failures !== []) {
            $hasPrimaryFailure = false;
            foreach ($failures as $failure) {
                if (str_contains($failure, 'provider:' . $primary) || str_contains($failure, 'model:' . $primary . ':')) {
                    $hasPrimaryFailure = true;
                    break;
                }
            }
            $status = $hasPrimaryFailure ? self::STATUS_CRITICAL : self::STATUS_DEGRADED;
        }

        $summary = $status === self::STATUS_OK
            ? 'Preflight IA saudável.'
            : 'Falhas detectadas em: ' . implode(', ', $failures);

        $db = Database::connect();
        $stmt = $db->prepare('INSERT INTO ai_preflight_checks (status, summary, providers_json, models_json, checked_at, created_at) VALUES (:status,:summary,:providers_json,:models_json,NOW(),NOW())');
        $stmt->execute([
            'status' => $status,
            'summary' => $summary,
            'providers_json' => json_encode($providerResults, JSON_UNESCAPED_UNICODE),
            'models_json' => json_encode($modelResults, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->currentStatus();
    }

    private function makeProvider(string $provider): AIProviderInterface
    {
        return match ($provider) {
            'gemini' => new GeminiProvider(),
            'openai', '' => new OpenAIProvider(),
            default => throw new \RuntimeException('Provider não suportado no preflight: ' . $provider),
        };
    }

    /** @return array<string,mixed> */
    private function checkTask(AIProviderInterface $provider, string $task): array
    {
        return match ($task) {
            'content' => $this->safeCheck(fn (): string => $provider->generate('Responda apenas: content_ok')),
            'refinement' => $this->safeCheck(fn (): string => $provider->refine('Texto curto de teste.')),
            'humanizer' => $this->safeCheck(fn (): string => $provider->humanize('Texto curto de teste.')),
            'structure' => $this->safeStructuredCheck($provider),
            default => ['ok' => false, 'error_type' => 'unknown', 'error' => 'task inválida'],
        };
    }

    /** @return array<string,mixed> */
    private function safeCheck(callable $fn): array
    {
        try {
            $result = trim((string) $fn());
            return ['ok' => $result !== '', 'result' => $result];
        } catch (Throwable $e) {
            return ['ok' => false, 'error_type' => $this->classifyError($e->getMessage()), 'error' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    private function safeStructuredCheck(AIProviderInterface $provider): array
    {
        try {
            $result = $provider->generateStructured('Retorne objeto de saúde.', [
                'type' => 'object',
                'required' => ['status'],
                'properties' => ['status' => ['type' => 'string']],
            ]);

            return ['ok' => is_array($result) && isset($result['status']), 'result' => $result];
        } catch (Throwable $e) {
            return ['ok' => false, 'error_type' => $this->classifyError($e->getMessage()), 'error' => $e->getMessage()];
        }
    }

    private function classifyError(string $message): string
    {
        $m = strtolower($message);
        return match (true) {
            str_contains($m, '401'), str_contains($m, 'auth'), str_contains($m, 'api_key') => 'auth',
            str_contains($m, 'quota'), str_contains($m, '429'), str_contains($m, 'rate') => 'quota',
            str_contains($m, 'model'), str_contains($m, 'not found') => 'invalid_model',
            str_contains($m, 'timeout') => 'timeout',
            default => 'unknown',
        };
    }

    private function registerFailureMetric(string $provider, string $type): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('INSERT INTO ai_preflight_failure_metrics (provider, failure_type, occurred_at, created_at) VALUES (:provider,:failure_type,NOW(),NOW())');
        $stmt->execute(['provider' => $provider, 'failure_type' => $type]);
    }
}
