<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use App\Repositories\PaymentRepository;
use Throwable;

final class PaymentStatusPollingService
{
    public function __construct(
        private readonly PaymentProviderInterface $provider = new DebitoMpesaProvider(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly DebitoStatusMapper $mapper = new DebitoStatusMapper(),
        private readonly PaymentStateTransitionService $transitions = new PaymentStateTransitionService(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {}

    /**
     * @return array{checked:int,updated:int,paid:int,errors:int}
     */
    public function run(): array
    {
        $summary = [
            'checked' => 0,
            'updated' => 0,
            'paid' => 0,
            'errors' => 0,
        ];

        $enabled = filter_var((string) Env::get('DEBITO_POLLING_ENABLED', true), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            $this->logger->info('Polling desativado por configuração', ['env' => 'DEBITO_POLLING_ENABLED']);
            return $summary;
        }

        foreach ($this->payments->findPendingForPolling() as $payment) {
            $summary['checked']++;

            try {
                $externalReference = trim((string) ($payment['external_reference'] ?? ''));
                if ($externalReference === '') {
                    $this->logger->error('Polling com pagamento sem external_reference', [
                        'payment_id' => (int) ($payment['id'] ?? 0),
                    ]);
                    $summary['errors']++;
                    continue;
                }

                $statusPayload = $this->provider->checkStatus($externalReference);
                $providerStatus = (string) ($statusPayload['provider_status'] ?? 'PENDING');
                $internalStatus = $this->mapper->map($providerStatus);

                $updated = $this->transitions->apply(
                    $payment,
                    $externalReference,
                    $internalStatus,
                    $providerStatus,
                    $statusPayload['raw'] ?? [],
                    'polling'
                );

                if ($updated) {
                    $summary['updated']++;
                    if ($internalStatus === 'paid') {
                        $summary['paid']++;
                    }
                }

                $this->logger->info('Polling status check', [
                    'payment_id' => (int) $payment['id'],
                    'provider_status' => $providerStatus,
                    'internal_status' => $internalStatus,
                ]);
            } catch (Throwable $e) {
                $summary['errors']++;
                $this->logger->error('Polling falhou para pagamento', [
                    'payment_id' => $payment['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }
}
