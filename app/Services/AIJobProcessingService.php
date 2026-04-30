<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Jobs\GenerateOrderDocumentJob;
use App\Repositories\AIJobRepository;
use App\Repositories\GeneratedDocumentRepository;

final class AIJobProcessingService
{
    public function __construct(
        private readonly AIProviderPreflightService $preflight = new AIProviderPreflightService(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
    ) {}

    /**
     * @return array{checked:int,processed:int,completed:int,failed:int,skipped:int,retried:int}
     */
    public function runBatch(?int $limit = null): array
    {
        Database::connect();
        $jobRunner = new GenerateOrderDocumentJob();
        $repo = new AIJobRepository();
        $documents = new GeneratedDocumentRepository();

        try {
            $this->preflight->assertQueueAllowed();
        } catch (\Throwable $e) {
            $status = $this->preflight->currentStatus();
            $this->logger->error('ai_job.processing.preflight_blocked', [
                'error' => $e->getMessage(),
                'preflight_status' => (string) ($status['status'] ?? 'critical'),
                'is_stale' => (bool) ($status['is_stale'] ?? true),
                'providers_checked' => array_keys((array) ($status['providers'] ?? [])),
                'last_check_at' => $status['last_check_at'] ?? null,
            ]);

            return [
                'checked' => 0,
                'processed' => 0,
                'completed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'retried' => 0,
                'blocked' => true,
                'reason' => $e->getMessage(),
            ];
        }

        $effectiveLimit = max(1, $limit ?? (int) ($_ENV['AI_JOB_BATCH_LIMIT'] ?? 5));
        $staleTimeout = max(300, (int) ($_ENV['AI_JOB_STALE_PROCESSING_TIMEOUT'] ?? 1800));
        $maxAttempts = max(1, (int) ($_ENV['AI_JOB_MAX_ATTEMPTS'] ?? 4));
        $jobs = $repo->reserveQueued($effectiveLimit, $staleTimeout);

        $summary = [
            'checked' => count($jobs),
            'processed' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'retried' => 0,
        ];

        foreach ($jobs as $row) {
            $summary['processed']++;
            $jobId = (int) $row['id'];
            $orderId = (int) $row['order_id'];
            $stage = (string) ($row['stage'] ?? 'unknown');
            $reservationToken = (string) ($row['reservation_token'] ?? '');

            if ($reservationToken === '' || !$repo->markProcessing($jobId, $reservationToken)) {
                $summary['skipped']++;
                $this->logger->info('ai_job.processing.skipped_reservation_lost', ['job_id' => $jobId, 'order_id' => $orderId]);
                continue;
            }

            if ($stage !== 'document_generation') {
                $summary['failed']++;
                $repo->markFailed($jobId, 'Stage não suportado: ' . $stage);
                $this->logger->error('ai_job.unsupported_stage', ['job_id' => $jobId, 'stage' => $stage]);
                continue;
            }

            $existingDoc = $documents->findLatestByOrderId($orderId);
            if (is_array($existingDoc) && $this->isReusableDocument($existingDoc)) {
                $result = [
                    'order_id' => $orderId,
                    'generated_document_id' => (int) ($existingDoc['id'] ?? 0),
                    'version' => (int) ($existingDoc['version'] ?? 1),
                    'file_path' => (string) ($existingDoc['file_path'] ?? ''),
                    'reused_existing_document' => true,
                    'queued_for_human_review' => (string) ($existingDoc['status'] ?? '') === 'pending_human_review',
                ];
                $repo->markCompleted($jobId, $result);
                $summary['completed']++;
                $this->logger->info('ai_job.processing.completed_existing_document', ['job_id' => $jobId, 'order_id' => $orderId]);
                continue;
            }

            $this->logger->info('ai_job.processing.started', ['job_id' => $jobId, 'order_id' => $orderId]);

            try {
                $result = $jobRunner->handle($orderId);
                $repo->markCompleted($jobId, $result);
                $summary['completed']++;
                $this->logger->info('ai_job.processing.completed', ['job_id' => $jobId, 'order_id' => $orderId, 'result' => $result]);
            } catch (\Throwable $e) {
                $attempts = (int) ($row['attempts'] ?? 0) + 1;
                if ($attempts < $maxAttempts) {
                    $delaySeconds = min(1800, (int) pow(2, max(1, $attempts)) * 60);
                    $repo->markRetryWait($jobId, $e->getMessage(), $delaySeconds);
                    $summary['retried']++;
                    $this->logger->error('ai_job.processing.retry_scheduled', [
                        'job_id' => $jobId,
                        'order_id' => $orderId,
                        'attempt' => $attempts,
                        'max_attempts' => $maxAttempts,
                        'retry_in_seconds' => $delaySeconds,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                $repo->markFailed($jobId, $e->getMessage());
                $summary['failed']++;
                $this->logger->error('ai_job.processing.failed_terminal', [
                    'job_id' => $jobId,
                    'order_id' => $orderId,
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    private function isReusableDocument(array $document): bool
    {
        $status = (string) ($document['status'] ?? '');
        if (!in_array($status, ['generated', 'approved', 'pending_human_review'], true)) {
            return false;
        }

        $path = trim((string) ($document['file_path'] ?? ''));
        if ($path === '') {
            return false;
        }

        $storage = new StoragePathService();
        try {
            $full = $storage->ensurePathInside($path, $storage->generatedBase());
        } catch (\RuntimeException) {
            return false;
        }

        return is_file($full) && filesize($full) > 0;
    }
}
