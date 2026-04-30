<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
use App\Helpers\Database;
$db = Database::connect();
$rows = $db->query("SELECT o.id, gd.id AS document_id, gd.file_path, gd.status AS doc_status
FROM orders o
LEFT JOIN generated_documents gd ON gd.order_id=o.id
WHERE o.status='delivery_blocked'
ORDER BY o.id DESC")->fetchAll();
$ready=0;$docFix=0;
foreach ($rows as $r) {
  $orderId=(int)$r['id'];$docId=(int)($r['document_id']??0);
  $hasActive=(int)$db->query("SELECT COUNT(*) FROM post_payment_exceptions WHERE order_id={$orderId} AND blocked_delivery=1 AND state IN ('open','in_review','awaiting_finance','awaiting_compliance')")->fetchColumn()>0;
  $hasCritical=(int)$db->query("SELECT COUNT(*) FROM document_compliance_validations dcv INNER JOIN generated_documents gd ON gd.id=dcv.generated_document_id WHERE gd.order_id={$orderId} AND dcv.critical_count > 0")->fetchColumn()>0;
  $validFile=false;
  $p=trim((string)($r['file_path']??'')); if($p!==''){ $full=realpath(__DIR__.'/../storage/generated/'.ltrim($p,'/')); $validFile=$full!==false && is_file($full) && filesize($full)>0; }
  if (!$hasActive && !$hasCritical && $validFile) {
    $db->exec("UPDATE orders SET status='ready' WHERE id={$orderId}"); $ready++;
    if ($docId>0 && (string)($r['doc_status']??'')==='rejected') { $db->exec("UPDATE generated_documents SET status='generated' WHERE id={$docId}"); $docFix++; }
  }
}
echo "orders corrigidas para ready: {$ready}\n";
echo "generated_documents corrigidos para generated: {$docFix}\n";
