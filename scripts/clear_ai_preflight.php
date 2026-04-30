<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
use App\Helpers\Database;
$db = Database::connect();
$a = $db->exec('DELETE FROM ai_preflight_checks');
$b = $db->exec('DELETE FROM ai_preflight_failure_metrics');
echo "ai_preflight_checks={$a}; ai_preflight_failure_metrics={$b}\n";
