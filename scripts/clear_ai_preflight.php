<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
use App\Helpers\Database;
$db = Database::connect();
$checks = (int) $db->exec('DELETE FROM ai_preflight_checks');
$metrics = (int) $db->exec('DELETE FROM ai_preflight_failure_metrics');
echo "ai_preflight_checks removidos: {$checks}\n";
echo "ai_preflight_failure_metrics removidos: {$metrics}\n";
