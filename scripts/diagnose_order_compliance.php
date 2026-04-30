<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
use App\Helpers\Database;
$id=(int)($argv[1]??0); if($id<=0){echo "Uso: php scripts/diagnose_order_compliance.php <order_id>\n";exit(1);} 
$db=Database::connect();
$o=$db->query("SELECT id,status FROM orders WHERE id={$id}")->fetch();
$d=$db->query("SELECT id,status,file_path FROM generated_documents WHERE order_id={$id} ORDER BY id DESC LIMIT 1")->fetch();
echo "order_status=".($o['status']??'n/a')."\n";
echo "document_status=".($d['status']??'n/a')."\n";
echo "file_path=".($d['file_path']??'')."\n";
if($d){$v=$db->query("SELECT summary_json,non_conformities_json FROM document_compliance_validations WHERE generated_document_id=".(int)$d['id']." ORDER BY id DESC LIMIT 1")->fetch(); echo "summary=".($v['summary_json']??'{}')."\n"; echo "non_conformities=".($v['non_conformities_json']??'[]')."\n";}
