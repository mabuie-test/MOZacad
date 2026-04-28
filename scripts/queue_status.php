<?php

declare(strict_types=1);

use App\Helpers\Database;

require_once __DIR__ . '/../bootstrap/app.php';

$db = Database::connect();
$staleSeconds = max(300, (int) ($_ENV['AI_JOB_STALE_PROCESSING_TIMEOUT'] ?? 1800));

$totals = $db->query('SELECT status, COUNT(*) AS total FROM ai_jobs GROUP BY status ORDER BY total DESC')->fetchAll();
$queued = $db->query("SELECT id, order_id, stage, attempts, created_at, updated_at FROM ai_jobs WHERE status='queued' ORDER BY created_at ASC LIMIT 50")->fetchAll();
$retryEligible = $db->query("SELECT id, order_id, attempts, next_retry_at, error_text FROM ai_jobs WHERE status='retry_wait' AND (next_retry_at IS NULL OR next_retry_at <= NOW()) ORDER BY COALESCE(next_retry_at, created_at) ASC LIMIT 50")->fetchAll();
$stuckProcessing = $db->query("SELECT id, order_id, status, processing_started_at, reserved_at, attempts FROM ai_jobs WHERE (status='processing' AND processing_started_at < DATE_SUB(NOW(), INTERVAL {$staleSeconds} SECOND)) OR (status='reserved' AND reserved_at < DATE_SUB(NOW(), INTERVAL 600 SECOND)) ORDER BY updated_at ASC LIMIT 50")->fetchAll();

$queuedNoOpenJob = $db->query("SELECT o.id, o.updated_at, o.title_or_theme FROM orders o LEFT JOIN ai_jobs j ON j.order_id=o.id AND j.stage='document_generation' AND j.status IN ('queued','pending','reserved','processing','retry_wait') WHERE o.status='queued' AND j.id IS NULL ORDER BY o.updated_at ASC LIMIT 50")->fetchAll();
$queuedCompletedNoDoc = $db->query("SELECT o.id, o.updated_at, j.id AS job_id, j.updated_at AS job_updated_at FROM orders o INNER JOIN ai_jobs j ON j.order_id=o.id AND j.stage='document_generation' AND j.status='completed' LEFT JOIN generated_documents gd ON gd.order_id=o.id WHERE o.status='queued' AND gd.id IS NULL ORDER BY j.updated_at DESC LIMIT 50")->fetchAll();

$lastJobs = $db->query('SELECT id, order_id, stage, status, attempts, next_retry_at, updated_at FROM ai_jobs ORDER BY id DESC LIMIT 10')->fetchAll();
$lastDocs = $db->query('SELECT id, order_id, version, status, file_path, created_at FROM generated_documents ORDER BY id DESC LIMIT 10')->fetchAll();

$printRows = static function (string $title, array $rows): void {
    echo "\n== {$title} ==\n";
    if ($rows === []) {
        echo "(vazio)\n";
        return;
    }

    foreach ($rows as $row) {
        echo '- ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
};

echo "QUEUE STATUS\n";
echo 'Gerado em: ' . date('c') . "\n";

$printRows('Total de jobs por status', $totals);
$printRows('Jobs queued', $queued);
$printRows('Jobs retry_wait elegíveis', $retryEligible);
$printRows('Jobs processing/reserved presos', $stuckProcessing);
$printRows('Orders queued sem job aberto', $queuedNoOpenJob);
$printRows('Orders queued com job completed sem documento', $queuedCompletedNoDoc);
$printRows('Últimos 10 jobs', $lastJobs);
$printRows('Últimos 10 documentos', $lastDocs);
