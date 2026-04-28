<?php

declare(strict_types=1);

use App\Services\ApplicationLoggerService;
use App\Services\WorkerOrchestrationService;

require_once __DIR__ . '/../bootstrap/app.php';

$argv = $_SERVER['argv'] ?? [];
$onceFlag = in_array('--once', $argv, true);
$runOnceEnv = filter_var((string) ($_ENV['WORKER_RUN_ONCE'] ?? false), FILTER_VALIDATE_BOOL);
$runOnce = $onceFlag || $runOnceEnv;
$interval = max(1, (int) ($_ENV['WORKER_LOOP_INTERVAL_SECONDS'] ?? 30));

$logger = new ApplicationLoggerService();
$workers = new WorkerOrchestrationService();

$loop = 0;
do {
    $loop++;
    $startedAt = date('c');
    $header = sprintf('[%s] Worker round #%d started', $startedAt, $loop);
    echo $header . PHP_EOL;
    $logger->info('worker.round.started', ['round' => $loop, 'run_once' => $runOnce, 'interval_seconds' => $interval]);

    $summary = $workers->runRound();
    $line = sprintf('[%s] Worker round #%d summary: %s', date('c'), $loop, json_encode($summary, JSON_UNESCAPED_UNICODE));
    echo $line . PHP_EOL;
    $logger->info('worker.round.finished', ['round' => $loop, 'summary' => $summary]);

    if ($runOnce) {
        break;
    }

    sleep($interval);
} while (true);
