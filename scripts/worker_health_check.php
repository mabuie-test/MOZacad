<?php

declare(strict_types=1);

use App\Services\WorkerHealthService;

require_once __DIR__ . '/../bootstrap/app.php';

$health = (new WorkerHealthService())->snapshot();
echo json_encode($health, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

if (!empty($health['stale_alert'])) {
    fwrite(STDERR, sprintf('ALERTA: worker sem heartbeat recente (> %d min).%s', (int) ($health['stale_threshold_minutes'] ?? 5), PHP_EOL));
    exit(1);
}
