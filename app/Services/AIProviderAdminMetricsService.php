<?php

declare(strict_types=1);

namespace App\Services;

final class AIProviderAdminMetricsService
{
    public function fallbackRateByProvider(): array
    {
        $file = (new StoragePathService())->logsBase() . '/application.log';
        if (!is_file($file)) {
            return [];
        }

        $attempts = [];
        $fallbacks = [];
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            if (!preg_match('/\] [A-Z]+ ([^ ]+) (\{.*\})$/u', $line, $matches)) {
                continue;
            }

            $event = trim((string) ($matches[1] ?? ''));
            $payload = json_decode((string) ($matches[2] ?? '{}'), true);
            if (!is_array($payload)) {
                continue;
            }

            if ($event === 'ai.provider.used') {
                $providerUsed = strtolower(trim((string) ($payload['provider'] ?? 'unknown')));
                $fallbackUsed = (($payload['fallback_used'] ?? false) === true);
                $primaryProvider = strtolower(trim((string) ($payload['primary_provider'] ?? $providerUsed)));
                if ($primaryProvider === '') {
                    $primaryProvider = 'unknown';
                }

                $attempts[$primaryProvider] = ($attempts[$primaryProvider] ?? 0) + 1;
                if ($fallbackUsed) {
                    $fallbacks[$primaryProvider] = ($fallbacks[$primaryProvider] ?? 0) + 1;
                }
            }
        }

        $rows = [];
        foreach ($attempts as $provider => $total) {
            $fallbackCount = (int) ($fallbacks[$provider] ?? 0);
            $rate = $total > 0 ? round(($fallbackCount / $total) * 100, 2) : 0.0;
            $rows[] = [
                'provider' => $provider,
                'total_used' => $total,
                'fallback_used' => $fallbackCount,
                'fallback_rate_pct' => $rate,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['provider'], (string) $b['provider']));
        return $rows;
    }
}
