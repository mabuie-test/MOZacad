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

$jobs = $repo->reserveQueued(5);
if ($jobs === []) {
    echo "Nenhum AI job pendente.\n";
    exit(0);
}

foreach ($jobs as $row) {
    $jobId = (int) $row['id'];
    $orderId = (int) $row['order_id'];
    $stage = (string) ($row['stage'] ?? 'unknown');

    if ($stage !== 'document_generation') {
        $repo->markFailed($jobId, 'Stage não suportado: ' . $stage);
        $logger->error('ai_job.unsupported_stage', ['job_id' => $jobId, 'stage' => $stage]);
        continue;
    }

    $repo->markProcessing($jobId);
    $logger->info('ai_job.processing.started', ['job_id' => $jobId, 'order_id' => $orderId]);

    try {
        $result = $jobRunner->handle($orderId);
        $repo->markCompleted($jobId, $result);
        $logger->info('ai_job.processing.completed', ['job_id' => $jobId, 'order_id' => $orderId, 'result' => $result]);
        echo sprintf("AI job %d concluído para order %d\n", $jobId, $orderId);
    } catch (Throwable $e) {
        $repo->markFailed($jobId, $e->getMessage());
        $logger->error('ai_job.processing.failed', ['job_id' => $jobId, 'order_id' => $orderId, 'error' => $e->getMessage()]);
        echo sprintf("AI job %d falhou: %s\n", $jobId, $e->getMessage());
    }
}
