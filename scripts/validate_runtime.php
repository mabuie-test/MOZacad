<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Helpers\Database;
use App\Services\SchemaConvergenceService;

$db = Database::connect();
$schema = (new SchemaConvergenceService())->enforce($db, false);

$env = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'production')));
$debugEnabled = filter_var((string) ($_ENV['APP_DEBUG'] ?? false), FILTER_VALIDATE_BOOL);
$allowUnsignedWebhook = filter_var((string) ($_ENV['DEBITO_ALLOW_UNSIGNED_WEBHOOK_LOCAL'] ?? false), FILTER_VALIDATE_BOOL);
if ($env === 'production' && $debugEnabled) {
    fwrite(STDERR, "[security] APP_DEBUG=true não permitido em produção.\n");
    exit(1);
}
if ($env === 'production' && $allowUnsignedWebhook) {
    fwrite(STDERR, "[security] DEBITO_ALLOW_UNSIGNED_WEBHOOK_LOCAL=true não permitido em produção.\n");
    exit(1);
}


$staleQueuedMinutes = max(1, (int) ($_ENV['QUEUE_STALE_QUEUED_MINUTES'] ?? 10));
$staleProcessingMinutes = max(1, (int) ($_ENV['QUEUE_STALE_PROCESSING_MINUTES'] ?? 30));


$storagePaths = new App\Services\StoragePathService();
$writableTargets = [
    'storage_logs_writable' => $storagePaths->logsBase(),
    'storage_generated_writable' => $storagePaths->generatedBase(),
    'storage_uploads_writable' => $storagePaths->uploadsBase(),
    'storage_norms_writable' => $storagePaths->normsBase(),
];

$checks = [
    'payment_without_invoice' => (int) $db->query('SELECT COUNT(*) FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id WHERE i.id IS NULL')->fetchColumn(),
    'provider_successful_not_paid' => (int) $db->query("SELECT COUNT(*) FROM payments WHERE UPPER(TRIM(COALESCE(provider_status, ''))) = 'SUCCESSFUL' AND status <> 'paid'")->fetchColumn(),
    'paid_payment_with_unpaid_invoice' => (int) $db->query("SELECT COUNT(*) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE p.status = 'paid' AND i.status <> 'paid'")->fetchColumn(),
    'paid_payment_with_order_pending_payment' => (int) $db->query("SELECT COUNT(*) FROM payments p INNER JOIN orders o ON o.id = p.order_id WHERE p.status = 'paid' AND o.status = 'pending_payment'")->fetchColumn(),
    'paid_without_job_or_document' => (int) $db->query("SELECT COUNT(*) FROM orders o LEFT JOIN ai_jobs j ON j.order_id=o.id LEFT JOIN generated_documents gd ON gd.order_id=o.id WHERE o.status IN ('queued','under_human_review','ready') AND j.id IS NULL AND gd.id IS NULL")->fetchColumn(),
    'paid_payment_without_job_or_document' => (int) $db->query("SELECT COUNT(*) FROM payments p
        INNER JOIN orders o ON o.id = p.order_id
        LEFT JOIN ai_jobs j ON j.order_id = o.id AND j.stage = 'document_generation'
        LEFT JOIN generated_documents gd ON gd.order_id = o.id
        WHERE p.status = 'paid' AND j.id IS NULL AND gd.id IS NULL")->fetchColumn(),
    'duplicate_open_document_generation_jobs' => (int) $db->query("SELECT COUNT(*) FROM (
        SELECT order_id, stage
        FROM ai_jobs
        WHERE stage = 'document_generation'
          AND status IN ('queued','pending','reserved','processing','retry_wait')
        GROUP BY order_id, stage
        HAVING COUNT(*) > 1
    ) d")->fetchColumn(),
    'open_review_without_pending_document' => (int) $db->query("SELECT COUNT(*) FROM human_review_queue q INNER JOIN generated_documents gd ON gd.id = q.generated_document_id WHERE q.status IN ('pending','assigned') AND gd.status <> 'pending_human_review'")->fetchColumn(),
    'revision_requested_without_rejected_document' => (int) $db->query("SELECT COUNT(*) FROM orders o LEFT JOIN generated_documents gd ON gd.order_id=o.id AND gd.status='returned_for_revision' WHERE o.status='revision_requested' AND gd.id IS NULL")->fetchColumn(),

    'queued_order_without_open_job_stale' => (int) $db->query("SELECT COUNT(*) FROM orders o
        LEFT JOIN ai_jobs j ON j.order_id=o.id AND j.stage='document_generation' AND j.status IN ('queued','pending','reserved','processing','retry_wait')
        WHERE o.status='queued' AND j.id IS NULL AND o.updated_at < DATE_SUB(NOW(), INTERVAL {$staleQueuedMinutes} MINUTE)")->fetchColumn(),
    'queued_order_with_queued_job_stale' => (int) $db->query("SELECT COUNT(*) FROM orders o
        INNER JOIN ai_jobs j ON j.order_id=o.id AND j.stage='document_generation' AND j.status='queued'
        WHERE o.status='queued' AND j.updated_at < DATE_SUB(NOW(), INTERVAL {$staleQueuedMinutes} MINUTE)")->fetchColumn(),
    'job_processing_stale' => (int) $db->query("SELECT COUNT(*) FROM ai_jobs WHERE status IN ('processing','reserved') AND COALESCE(processing_started_at, reserved_at, updated_at) < DATE_SUB(NOW(), INTERVAL {$staleProcessingMinutes} MINUTE)")->fetchColumn(),
    'job_retry_wait_overdue' => (int) $db->query("SELECT COUNT(*) FROM ai_jobs WHERE status='retry_wait' AND next_retry_at IS NOT NULL AND next_retry_at <= NOW()")->fetchColumn(),
    'job_completed_without_generated_document' => (int) $db->query("SELECT COUNT(*) FROM ai_jobs j LEFT JOIN generated_documents gd ON gd.order_id=j.order_id WHERE j.stage='document_generation' AND j.status='completed' AND gd.id IS NULL")->fetchColumn(),
    'generated_document_file_missing' => 0,
    'generated_document_file_zero_bytes' => 0,
    'coupon_usage_without_coupon' => (int) $db->query('SELECT COUNT(*) FROM coupon_usage_logs c LEFT JOIN coupons cp ON cp.id = c.coupon_id WHERE cp.id IS NULL')->fetchColumn(),
];


$generatedRows = $db->query('SELECT id, file_path FROM generated_documents ORDER BY id DESC LIMIT 1000')->fetchAll();
$missingFiles = 0;
$zeroByteFiles = 0;
$generatedBase = realpath(__DIR__ . '/../storage/generated') ?: (__DIR__ . '/../storage/generated');
foreach ($generatedRows as $row) {
    $relative = trim((string) ($row['file_path'] ?? ''));
    if ($relative === '') {
        $missingFiles++;
        continue;
    }

    $candidate = $relative;
    if (!str_starts_with($candidate, '/')) {
        $candidate = $generatedBase . '/' . ltrim($candidate, '/');
    }

    $real = realpath($candidate);
    if ($real === false || !is_file($real)) {
        $missingFiles++;
        continue;
    }

    if (filesize($real) <= 0) {
        $zeroByteFiles++;
    }
}
$checks['generated_document_file_missing'] = $missingFiles;
$checks['generated_document_file_zero_bytes'] = $zeroByteFiles;


foreach ($writableTargets as $checkName => $targetPath) {
    try {
        $storagePaths->ensureDirectory($targetPath);
        $checks[$checkName] = is_writable($targetPath) ? 0 : 1;
    } catch (Throwable) {
        $checks[$checkName] = 1;
    }
}

$logsBase = $storagePaths->logsBase();
$logFiles = ['worker-cron.log', 'application.log'];
$maxLogMb = max(5, (int) ($_ENV['RUNTIME_MAX_LOG_FILE_SIZE_MB'] ?? 100));
foreach ($logFiles as $logFile) {
    $fullPath = $logsBase . '/' . $logFile;
    $checkKey = 'log_rotation_risk_' . str_replace('.', '_', $logFile);
    if (!is_file($fullPath)) {
        $checks[$checkKey] = 0;
        continue;
    }

    $checks[$checkKey] = filesize($fullPath) > ($maxLogMb * 1024 * 1024) ? 1 : 0;
}

$hasIssue = false;
if ($schema['issues'] !== []) {
    $hasIssue = true;
    echo "[schema] issues:\n";
    foreach ($schema['issues'] as $issue) {
        echo " - {$issue}\n";
    }
}

foreach ($checks as $name => $count) {
    echo sprintf("[check] %s=%d\n", $name, $count);
    if ($count > 0) {
        $hasIssue = true;
    }
}

if ($hasIssue) {
    fwrite(STDERR, "Validação operacional encontrou inconsistências.\n");
    exit(1);
}

echo "Validação operacional concluída sem inconsistências.\n";
