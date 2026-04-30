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

    $logger->info('ai.preflight.completed', [
        'status' => $status,
        'is_stale' => $isStale,
        'last_check_at' => $result['last_check_at'] ?? null,
        'providers_checked' => array_keys($providers),
    ]);

    if ($status === 'ok' && $isStale === false) {
        $logger->info('ai.preflight.success', [
            'status' => $status,
            'last_check_at' => $result['last_check_at'] ?? null,
            'providers' => array_keys($providers),
        ]);
    } else {
        foreach ($providers as $provider => $providerResult) {
            $providerResult = is_array($providerResult) ? $providerResult : [];
            if (($providerResult['ok'] ?? false) === true) {
                continue;
            }

            $logger->error('ai.preflight.failure', [
                'status' => $status,
                'provider' => (string) $provider,
                'error_type' => (string) ($providerResult['error_type'] ?? 'unknown'),
                'error' => (string) ($providerResult['error'] ?? 'erro não informado'),
                'is_stale' => $isStale,
            ]);
        }
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(($status === 'critical' || $isStale) ? 1 : 0);
} catch (Throwable $exception) {
    $logger->alert('ai.preflight.execution_error', [
        'error_type' => 'execution',
        'error' => $exception->getMessage(),
    ]);

    fwrite(STDERR, 'Falha ao executar preflight: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
