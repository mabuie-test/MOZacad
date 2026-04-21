<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;

final class PaymentStatusPollingService
{
    public function __construct(
        private readonly PaymentProviderInterface $provider = new DebitoMpesaProvider(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly DebitoStatusMapper $mapper = new DebitoStatusMapper(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {}

    public function run(): void
    {
        foreach ($this->payments->findPendingForPolling() as $payment) {
            if (empty($payment['external_reference'])) {
                continue;
            }

            $statusPayload = $this->provider->checkStatus($payment['external_reference']);
            $providerStatus = (string)($statusPayload['status'] ?? 'pending_confirmation');
            $internal = $this->mapper->map($providerStatus);

            if ($internal === 'paid') {
                $this->payments->markPaid((int)$payment['id'], $providerStatus);
                $this->orders->updateStatus((int)$payment['order_id'], 'queued');
            } else {
                $this->payments->updateStatus((int)$payment['id'], $internal, $providerStatus);
            }

            $this->logger->info('Polling status check', [
                'payment_id' => $payment['id'],
                'provider_status' => $providerStatus,
                'internal_status' => $internal,
            ]);
        }
    }
}
