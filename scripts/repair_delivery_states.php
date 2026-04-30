<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
use App\Helpers\Database;
$db=Database::connect();
$rows=$db->query("SELECT o.id order_id, gd.id document_id, gd.file_path FROM orders o LEFT JOIN generated_documents gd ON gd.order_id=o.id WHERE o.status='delivery_blocked' ORDER BY gd.id DESC")->fetchAll();
$fixed=0; foreach($rows as $r){$critical=(int)$db->query("SELECT COUNT(*) FROM document_compliance_validations WHERE generated_document_id=".(int)$r['document_id']." AND JSON_EXTRACT(summary_json,'$.critical') > 0")->fetchColumn(); if($critical===0){$db->exec("UPDATE generated_documents SET status='generated' WHERE id=".(int)$r['document_id']);$db->exec("UPDATE orders SET status='ready' WHERE id=".(int)$r['order_id']);$fixed++;}}
echo "fixed={$fixed}\n";
