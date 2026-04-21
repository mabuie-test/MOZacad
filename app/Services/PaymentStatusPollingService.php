<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\DebitoTransactionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentStatusLogRepository;
use Throwable;

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

                $db = Database::connect();
                $db->beginTransaction();

                try {
                    $this->payments->updateStatus(
                        (int) $payment['id'],
                        $internalStatus,
                        $providerStatus,
                        (string) ($statusPayload['provider_message'] ?? null)
                    );
                    $this->debitoTransactions->updateStatusByReference($externalReference, $internalStatus, $statusPayload['raw'] ?? []);
                    $this->paymentStatusLogs->create(
                        (int) $payment['id'],
                        $internalStatus,
                        $providerStatus,
                        $statusPayload['raw'] ?? [],
                        'polling'
                    );

                    if ($internalStatus === 'paid') {
                        $this->payments->markPaid((int) $payment['id'], $providerStatus);
                        $this->invoices->markPaidById((int) $payment['invoice_id']);
                        $this->orders->updateStatus((int) $payment['order_id'], 'queued');
                        $summary['paid']++;
                    }

                    $db->commit();
                    $summary['updated']++;
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $e;
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
