<?php

declare(strict_types=1);

use App\Services\ApplicationLoggerService;
use App\Services\AIConfigBootstrapValidator;
use App\Services\WorkerOrchestrationService;
use App\Services\WorkerHealthService;

require_once __DIR__ . '/../bootstrap/app.php';

try {
    AIConfigBootstrapValidator::validate();
} catch (\Throwable $exception) {
    $message = 'Falha crítica de configuração de IA ao iniciar worker. Corrija AI_PROVIDER, AI_PROVIDER_MODE e chaves OPENAI_API_KEY/GEMINI_API_KEY no .env antes de reexecutar.';
    error_log($message . ' Detalhe: ' . $exception->getMessage());
    fwrite(STDERR, $message . PHP_EOL . 'Detalhe técnico: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$argv = $_SERVER['argv'] ?? [];
$onceFlag = in_array('--once', $argv, true);
$runOnceEnv = filter_var((string) ($_ENV['WORKER_RUN_ONCE'] ?? false), FILTER_VALIDATE_BOOL);
$runOnce = $onceFlag || $runOnceEnv;
$interval = max(1, (int) ($_ENV['WORKER_LOOP_INTERVAL_SECONDS'] ?? 30));

$logger = new ApplicationLoggerService();
$workers = new WorkerOrchestrationService();
$health = new WorkerHealthService();

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
    $health->touchHeartbeat($summary, $startedAt);

    if ($runOnce) {
        break;
    }

    sleep($interval);
} while (true);
