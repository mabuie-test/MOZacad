<?php

declare(strict_types=1);

use App\Helpers\Database;
use App\Jobs\GenerateOrderDocumentJob;
use App\Repositories\AIJobRepository;
use App\Services\ApplicationLoggerService;

require_once __DIR__ . '/../bootstrap/app.php';

Database::connect();
$jobRunner = new GenerateOrderDocumentJob();
$repo = new AIJobRepository();
$logger = new ApplicationLoggerService();

$limit = max(1, (int) ($_ENV['AI_JOB_BATCH_LIMIT'] ?? 5));
$staleTimeout = max(300, (int) ($_ENV['AI_JOB_STALE_PROCESSING_TIMEOUT'] ?? 1800));
$maxAttempts = max(1, (int) ($_ENV['AI_JOB_MAX_ATTEMPTS'] ?? 4));
$jobs = $repo->reserveQueued($limit, $staleTimeout);
if ($jobs === []) {
    echo "Nenhum AI job pendente.\n";
    exit(0);
}

foreach ($jobs as $row) {
    $jobId = (int) $row['id'];
    $orderId = (int) $row['order_id'];
    $stage = (string) ($row['stage'] ?? 'unknown');
    $reservationToken = (string) ($row['reservation_token'] ?? '');

    if ($reservationToken === '' || !$repo->markProcessing($jobId, $reservationToken)) {
        $logger->info('ai_job.processing.skipped_reservation_lost', ['job_id' => $jobId, 'order_id' => $orderId]);
        continue;
    }

    if ($stage !== 'document_generation') {
        $repo->markFailed($jobId, 'Stage não suportado: ' . $stage);
        $logger->error('ai_job.unsupported_stage', ['job_id' => $jobId, 'stage' => $stage]);
        continue;
    }

    $logger->info('ai_job.processing.started', ['job_id' => $jobId, 'order_id' => $orderId]);

    try {
        $result = $jobRunner->handle($orderId);
        $repo->markCompleted($jobId, $result);
        $logger->info('ai_job.processing.completed', ['job_id' => $jobId, 'order_id' => $orderId, 'result' => $result]);
        echo sprintf("AI job %d concluído para order %d\n", $jobId, $orderId);
    } catch (Throwable $e) {
        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        if ($attempts < $maxAttempts) {
            $delaySeconds = min(1800, (int) pow(2, max(1, $attempts)) * 60);
            $repo->markRetryWait($jobId, $e->getMessage(), $delaySeconds);
            $logger->error('ai_job.processing.retry_scheduled', [
                'job_id' => $jobId,
                'order_id' => $orderId,
                'attempt' => $attempts,
                'max_attempts' => $maxAttempts,
                'retry_in_seconds' => $delaySeconds,
                'error' => $e->getMessage(),
            ]);
            echo sprintf("AI job %d falhou (tentativa %d/%d). Reagendado em %ds.\n", $jobId, $attempts, $maxAttempts, $delaySeconds);
            continue;
        }

        $repo->markFailed($jobId, $e->getMessage());
        $logger->error('ai_job.processing.failed_terminal', [
            'job_id' => $jobId,
            'order_id' => $orderId,
            'attempt' => $attempts,
            'max_attempts' => $maxAttempts,
            'error' => $e->getMessage(),
        ]);
        echo sprintf("AI job %d falhou definitivamente após %d tentativas: %s\n", $jobId, $attempts, $e->getMessage());
    }
}
