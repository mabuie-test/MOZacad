<?php

declare(strict_types=1);

namespace App\Services;

final class WorkerOrchestrationService
{
    public function __construct(
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
        private readonly ReconcileSuccessfulPaymentsService $reconcile = new ReconcileSuccessfulPaymentsService(),
        private readonly PaymentStatusPollingService $polling = new PaymentStatusPollingService(),
        private readonly AIJobProcessingService $aiJobs = new AIJobProcessingService(),
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function runRound(): array
    {
        $summary = [
            'reconcile' => null,
            'poll' => null,
            'ai_jobs' => null,
            'errors' => [],
        ];

        $summary['reconcile'] = $this->runStep('reconcile_successful_payments', fn (): array => $this->reconcile->run(), $summary['errors']);
        $summary['poll'] = $this->runStep('poll_payments', fn (): array => $this->polling->run(), $summary['errors']);
        $summary['ai_jobs'] = $this->runStep('process_ai_jobs', fn (): array => $this->aiJobs->runBatch(), $summary['errors']);

        return $summary;
    }

    /**
     * @param array<int,array{step:string,error:string}> $errors
     * @return array<string,mixed>
     */
    private function runStep(string $step, callable $fn, array &$errors): array
    {
        try {
            $started = microtime(true);
            $result = $fn();
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $payload = is_array($result) ? $result : ['result' => $result];
            $payload['duration_ms'] = $durationMs;
            $this->logger->info('worker.step.completed', ['step' => $step] + $payload);

            return $payload;
        } catch (\Throwable $e) {
            $errors[] = ['step' => $step, 'error' => $e->getMessage()];
            $this->logger->error('worker.step.failed', ['step' => $step, 'error' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }
}
