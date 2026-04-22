<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\DebitoTransactionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentStatusLogRepository;
use RuntimeException;
use Throwable;

final class PaymentStateTransitionService
{
    public function __construct(
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly DebitoTransactionRepository $debitoTransactions = new DebitoTransactionRepository(),
        private readonly PaymentStatusLogRepository $paymentStatusLogs = new PaymentStatusLogRepository(),
        private readonly InvoiceRepository $invoices = new InvoiceRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly AIJobDispatchService $dispatcher = new AIJobDispatchService(),
    ) {}

    public function apply(array $payment, string $reference, string $internalStatus, string $providerStatus, array $rawPayload, string $source): bool
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        if ($paymentId <= 0) {
            throw new RuntimeException('Pagamento inválido para transição de estado.');
        }

        $currentStatus = (string) ($payment['status'] ?? 'pending');
        if ($this->shouldIgnoreStatusChange($currentStatus, $internalStatus)) {
            $this->paymentStatusLogs->create($paymentId, $currentStatus, $providerStatus, $rawPayload, $source . ':ignored');
            return false;
        }

        $db = Database::connect();
        $db->beginTransaction();

        try {
            $lockedOrder = $this->orders->lockByIdForUpdate((int) $payment['order_id']);
            $this->payments->updateStatus($paymentId, $internalStatus, $providerStatus, (string) ($rawPayload['message'] ?? null));
            $this->debitoTransactions->updateStatusByReference($reference, $internalStatus, $rawPayload);
            $this->paymentStatusLogs->create($paymentId, $internalStatus, $providerStatus, $rawPayload, $source);

            if ($internalStatus === 'paid') {
                $this->payments->markPaid($paymentId, $providerStatus);
                $this->invoices->markStatusById((int) $payment['invoice_id'], 'paid');
                $this->orders->updateStatus((int) $payment['order_id'], 'queued');
                if (is_array($lockedOrder)) {
                    $this->dispatcher->enqueueDocumentGeneration($lockedOrder, $payment, $source);
                }
            } elseif (in_array($internalStatus, ['failed', 'cancelled', 'expired'], true)) {
                $this->invoices->markStatusById((int) $payment['invoice_id'], 'pending');
                $this->orders->updateStatus((int) $payment['order_id'], 'pending_payment');
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function shouldIgnoreStatusChange(string $currentStatus, string $incomingStatus): bool
    {
        $normalizedCurrent = strtolower(trim($currentStatus));
        $normalizedIncoming = strtolower(trim($incomingStatus));

        if ($normalizedCurrent === 'paid' && $normalizedIncoming !== 'paid') {
            return true;
        }

        if (in_array($normalizedCurrent, ['failed', 'cancelled', 'expired'], true) && $normalizedIncoming === 'pending') {
            return true;
        }

        return $normalizedCurrent === $normalizedIncoming;
    }
}
