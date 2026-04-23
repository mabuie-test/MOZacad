<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Helpers\Database;
use App\Services\SchemaConvergenceService;

$db = Database::connect();
$schema = (new SchemaConvergenceService())->enforce($db, false);

$checks = [
    'payment_without_invoice' => (int) $db->query('SELECT COUNT(*) FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id WHERE i.id IS NULL')->fetchColumn(),
    'paid_without_job_or_document' => (int) $db->query("SELECT COUNT(*) FROM orders o LEFT JOIN ai_jobs j ON j.order_id=o.id LEFT JOIN generated_documents gd ON gd.order_id=o.id WHERE o.status IN ('queued','under_human_review','ready') AND j.id IS NULL AND gd.id IS NULL")->fetchColumn(),
    'open_review_without_pending_document' => (int) $db->query("SELECT COUNT(*) FROM human_review_queue q INNER JOIN generated_documents gd ON gd.id = q.generated_document_id WHERE q.status IN ('pending','assigned') AND gd.status <> 'pending_human_review'")->fetchColumn(),
    'revision_requested_without_rejected_document' => (int) $db->query("SELECT COUNT(*) FROM orders o LEFT JOIN generated_documents gd ON gd.order_id=o.id AND gd.status='returned_for_revision' WHERE o.status='revision_requested' AND gd.id IS NULL")->fetchColumn(),
    'coupon_usage_without_coupon' => (int) $db->query('SELECT COUNT(*) FROM coupon_usage_logs c LEFT JOIN coupons cp ON cp.id = c.coupon_id WHERE cp.id IS NULL')->fetchColumn(),
];

$hasIssue = false;
if ($schema['issues'] !== []) {
    $hasIssue = true;
    echo "[schema] issues:
";
    foreach ($schema['issues'] as $issue) {
        echo " - {$issue}
";
    }
}

foreach ($checks as $name => $count) {
    echo sprintf('[check] %s=%d
', $name, $count);
    if ($count > 0) {
        $hasIssue = true;
    }
}

if ($hasIssue) {
    fwrite(STDERR, "Validação operacional encontrou inconsistências.
");
    exit(1);
}

echo "Validação operacional concluída sem inconsistências.
";
