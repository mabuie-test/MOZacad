<?php

declare(strict_types=1);

use App\Helpers\Database;
use App\Jobs\GenerateOrderDocumentJob;

require_once __DIR__ . '/../bootstrap/app.php';

$db = Database::connect();
$job = new GenerateOrderDocumentJob();

$stmt = $db->query("SELECT * FROM ai_jobs WHERE status IN ('queued','pending') ORDER BY created_at ASC LIMIT 5");
$jobs = $stmt->fetchAll();

if ($jobs === []) {
    echo "Nenhum AI job pendente.\n";
    exit(0);
}

foreach ($jobs as $row) {
    $jobId = (int) $row['id'];
    $orderId = (int) $row['order_id'];

    $db->prepare("UPDATE ai_jobs SET status='processing', updated_at=NOW() WHERE id=:id")
        ->execute(['id' => $jobId]);

    try {
        $result = $job->handle($orderId);

        $db->prepare("UPDATE ai_jobs SET status='completed', result_json=:result_json, updated_at=NOW() WHERE id=:id")
            ->execute([
                'id' => $jobId,
                'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]);

        echo sprintf("AI job %d concluído para order %d\n", $jobId, $orderId);
    } catch (Throwable $e) {
        $db->prepare("UPDATE ai_jobs SET status='failed', error_text=:error_text, updated_at=NOW() WHERE id=:id")
            ->execute([
                'id' => $jobId,
                'error_text' => $e->getMessage(),
            ]);

        echo sprintf("AI job %d falhou: %s\n", $jobId, $e->getMessage());
    }
}
