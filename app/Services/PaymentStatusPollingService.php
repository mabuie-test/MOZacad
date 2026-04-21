<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DebitoTransactionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentStatusLogRepository;

final class PaymentStatusPollingService
{
    public function __construct(
        private readonly PaymentProviderInterface $provider = new DebitoMpesaProvider(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly InvoiceRepository $invoices = new InvoiceRepository(),
        private readonly DebitoTransactionRepository $debitoTransactions = new DebitoTransactionRepository(),
        private readonly PaymentStatusLogRepository $paymentStatusLogs = new PaymentStatusLogRepository(),
        private readonly DebitoStatusMapper $mapper = new DebitoStatusMapper(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {}

    public function run(): void
    {
        foreach ($this->payments->findPendingForPolling() as $payment) {
            try {
                $externalReference = (string) ($payment['external_reference'] ?? '');
                if ($externalReference === '') {
                    continue;
                }

                $statusPayload = $this->provider->checkStatus($externalReference);
                $providerStatus = (string) ($statusPayload['provider_status'] ?? 'PENDING');
                $internal = $this->mapper->map($providerStatus);

                $this->payments->updateStatus((int) $payment['id'], $internal, $providerStatus, $statusPayload['provider_message'] ?? null);
                $this->debitoTransactions->updateStatusByReference($externalReference, $internal, $statusPayload['raw'] ?? []);
                $this->paymentStatusLogs->create((int) $payment['id'], $internal, $providerStatus, $statusPayload['raw'] ?? [], 'polling');

                if ($internal === 'paid') {
                    $this->payments->markPaid((int) $payment['id'], $providerStatus);
                    $this->invoices->markPaidById((int) $payment['invoice_id']);
                    $this->orders->updateStatus((int) $payment['order_id'], 'queued');
                }

                $this->logger->info('Polling status check', [
                    'payment_id' => $payment['id'],
                    'provider_status' => $providerStatus,
                    'internal_status' => $internal,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Polling falhou para pagamento', [
                    'payment_id' => $payment['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
