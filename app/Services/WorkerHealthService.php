<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use PDO;

final class WorkerHealthService
{
    public function __construct(
        private readonly StoragePathService $paths = new StoragePathService(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
    ) {}

    public function touchHeartbeat(array $summary = [], ?string $startedAt = null): void
    {
        $generatedBase = $this->paths->generatedBase();
        $this->paths->ensureDirectory($generatedBase);

        $payload = [
            'last_heartbeat_at' => date('c'),
            'round_started_at' => $startedAt,
            'summary' => $summary,
        ];

        @file_put_contents($generatedBase . '/worker-heartbeat.json', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function snapshot(): array
    {
        $db = Database::connect();
        $heartbeat = $this->readHeartbeat();
        $queue = $this->queueLag($db);
        $minutesSince = $this->minutesSince((string) ($heartbeat['last_heartbeat_at'] ?? ''));
        $threshold = max(1, (int) ($_ENV['WORKER_ALERT_STALE_MINUTES'] ?? 5));
        $stale = $minutesSince === null ? true : $minutesSince > $threshold;

        if ($stale) {
            $this->logger->alert('worker.heartbeat.stale', [
                'minutes_since_last_heartbeat' => $minutesSince,
                'threshold_minutes' => $threshold,
                'queue_lag_minutes' => $queue['queue_lag_minutes'],
            ]);
        }

        return [
            'last_heartbeat_at' => $heartbeat['last_heartbeat_at'] ?? null,
            'minutes_since_last_heartbeat' => $minutesSince,
            'queue_lag_minutes' => $queue['queue_lag_minutes'],
            'queued_jobs' => $queue['queued_jobs'],
            'stale_alert' => $stale,
            'stale_threshold_minutes' => $threshold,
        ];
    }

    private function readHeartbeat(): array
    {
        $file = $this->paths->generatedBase() . '/worker-heartbeat.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function queueLag(PDO $db): array
    {
        $queued = (int) $db->query("SELECT COUNT(*) FROM ai_jobs WHERE status='queued'")->fetchColumn();
        $oldest = $db->query("SELECT MIN(created_at) FROM ai_jobs WHERE status='queued'")->fetchColumn();
        $lag = $this->minutesSince((string) ($oldest ?: ''));

        return ['queued_jobs' => $queued, 'queue_lag_minutes' => $lag ?? 0];
    }

    private function minutesSince(string $isoDatetime): ?int
    {
        if (trim($isoDatetime) === '') {
            return null;
        }
        $ts = strtotime($isoDatetime);
        if ($ts === false) {
            return null;
        }
        return max(0, (int) floor((time() - $ts) / 60));
    }
}
