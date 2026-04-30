<?php

declare(strict_types=1);

use App\Services\AIProviderPreflightService;
use App\Services\ApplicationLoggerService;

require_once __DIR__ . '/../bootstrap/app.php';

$logger = new ApplicationLoggerService();
$preflight = new AIProviderPreflightService();

try {
    $result = $preflight->runAndPersist();

    $status = (string) ($result['status'] ?? 'critical');
    $isStale = (bool) ($result['is_stale'] ?? true);
    $providers = (array) ($result['providers'] ?? []);
    $models = (array) ($result['models'] ?? []);

    $severity = match ($status) {
        'ok' => 'ok',
        'degraded' => 'warn',
        default => 'critical',
    };

    $logger->info('ai.preflight.completed', [
        'status' => $status,
        'severity' => $severity,
        'is_stale' => $isStale,
        'last_check_at' => $result['last_check_at'] ?? null,
        'providers_checked' => array_keys($providers),
    ]);

    foreach ($providers as $provider => $providerResult) {
        $providerResult = is_array($providerResult) ? $providerResult : [];
        if (($providerResult['ok'] ?? false) === true) {
            continue;
        }

        $logger->error('ai.preflight.failure', [
            'status' => $status,
            'severity' => $severity,
            'provider' => (string) $provider,
            'failure_scope' => 'provider',
            'failure_type' => (string) ($providerResult['error_type'] ?? 'unknown'),
            'error' => (string) ($providerResult['error'] ?? 'erro não informado'),
            'is_stale' => $isStale,
        ]);
    }

    foreach ($models as $providerTask => $modelResult) {
        $modelResult = is_array($modelResult) ? $modelResult : [];
        if (($modelResult['ok'] ?? false) === true) {
            continue;
        }

        [$provider, $task] = array_pad(explode(':', (string) $providerTask, 2), 2, 'unknown');

        $logger->error('ai.preflight.failure', [
            'status' => $status,
            'severity' => $severity,
            'provider' => $provider,
            'failure_scope' => 'model',
            'failure_task' => $task,
            'failure_type' => (string) ($modelResult['error_type'] ?? 'unknown'),
            'error' => (string) ($modelResult['error'] ?? 'erro não informado'),
            'is_stale' => $isStale,
        ]);
    }

    if ($status === 'ok' && $isStale === false) {
        $logger->info('ai.preflight.success', [
            'status' => $status,
            'severity' => $severity,
            'last_check_at' => $result['last_check_at'] ?? null,
            'providers' => array_keys($providers),
        ]);
    }

    if ($isStale) {
        $logger->alert('ai.preflight.stale_block', [
            'status' => $status,
            'severity' => 'critical',
            'message' => (string) ($result['message'] ?? 'Preflight stale: bloqueio preventivo de novos jobs ativo.'),
            'preemptive_block' => true,
        ]);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(($status === 'critical' || $isStale) ? 1 : 0);
} catch (Throwable $exception) {
    $logger->alert('ai.preflight.execution_error', [
        'failure_scope' => 'execution',
        'failure_type' => 'execution',
        'error' => $exception->getMessage(),
    ]);

    fwrite(STDERR, 'Falha ao executar preflight: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
