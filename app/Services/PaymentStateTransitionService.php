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
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
    ) {}

    public function apply(array $payment, string $reference, string $internalStatus, string $providerStatus, array $rawPayload, string $source): bool
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        if ($paymentId <= 0) {
            throw new RuntimeException('Pagamento inválido para transição de estado.');
        }

        $db = Database::connect();
        $db->beginTransaction();

        try {
            $lockedPayment = $this->payments->lockByIdForUpdate($paymentId);
            if ($lockedPayment === null) {
                throw new RuntimeException('Pagamento não encontrado durante transição.');
            }

            $currentStatus = (string) ($lockedPayment['status'] ?? 'pending');
            if ($this->shouldIgnoreStatusChange($currentStatus, $internalStatus)) {
                $this->paymentStatusLogs->create($paymentId, $currentStatus, $providerStatus, $rawPayload, $source . ':ignored');
                $db->commit();
                return false;
            }

            $lockedOrder = $this->orders->lockByIdForUpdate((int) $lockedPayment['order_id']);
            $this->payments->updateStatus($paymentId, $internalStatus, $providerStatus, (string) ($rawPayload['message'] ?? null));
            $this->debitoTransactions->updateStatusByReference($reference, $internalStatus, $rawPayload);
            $this->paymentStatusLogs->create($paymentId, $internalStatus, $providerStatus, $rawPayload, $source);

            if ($internalStatus === 'paid') {
                $this->logger->info('payment.transition.paid', ['payment_id' => $paymentId, 'order_id' => (int) $lockedPayment['order_id'], 'source' => $source]);
                $this->payments->markPaid($paymentId, $providerStatus);
                $this->invoices->markStatusById((int) $lockedPayment['invoice_id'], 'paid');
                if ($this->canMoveOrderToQueued($lockedOrder)) {
                    $this->orders->updateStatus((int) $lockedPayment['order_id'], 'queued');
                }
                if (is_array($lockedOrder)) {
                    $this->dispatcher->enqueueDocumentGeneration($lockedOrder, $lockedPayment, $source);
                }
            } elseif (in_array($internalStatus, ['failed', 'cancelled', 'expired'], true)) {
                $this->logger->error('payment.transition.failed_like', ['payment_id' => $paymentId, 'status' => $internalStatus, 'source' => $source]);
                if (!$this->isOrderBeyondPayment((string) ($lockedOrder['status'] ?? ''))) {
                    $this->invoices->markStatusById((int) $lockedPayment['invoice_id'], 'pending');
                    $this->orders->updateStatus((int) $lockedPayment['order_id'], 'pending_payment');
                }
            }

            $db->commit();
            $this->logger->info('payment.transition.updated', ['payment_id' => $paymentId, 'from' => $currentStatus, 'to' => $internalStatus, 'source' => $source]);
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

    private function canMoveOrderToQueued(?array $order): bool
    {
        if (!is_array($order)) {
            return false;
        }

        $status = (string) ($order['status'] ?? '');
        return !in_array($status, ['queued', 'under_human_review', 'ready', 'revision_requested'], true);
    }

    private function isOrderBeyondPayment(string $status): bool
    {
        return in_array($status, ['queued', 'under_human_review', 'ready', 'revision_requested'], true);
    }
}
