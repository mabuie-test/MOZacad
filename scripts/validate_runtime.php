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
    'open_review_without_pending_document' => (int) $db->query("SELECT COUNT(*) FROM human_review_queue q INNER JOIN generated_documents gd ON gd.id = q.generated_document_id WHERE q.status IN ('pending','assigned') AND gd.status <> 'pending_human_review'")->fetchColumn(),
    'revision_requested_without_rejected_document' => (int) $db->query("SELECT COUNT(*) FROM orders o LEFT JOIN generated_documents gd ON gd.order_id=o.id AND gd.status='returned_for_revision' WHERE o.status='revision_requested' AND gd.id IS NULL")->fetchColumn(),

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
