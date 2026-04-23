<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\InvoiceRepository;
use App\Helpers\Env;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use RuntimeException;

final class OrderPaymentFlowService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly InvoiceRepository $invoices = new InvoiceRepository(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly InvoiceService $invoiceService = new InvoiceService(),
        private readonly PaymentService $paymentService = new PaymentService(),
    ) {}

    public function initiateOrderPayment(int $orderId, int $userId, string $msisdn, ?string $callbackUrl = null, ?string $internalNotes = null): array
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $order = $this->orders->lockByIdForUpdate($orderId);
            if ($order === null || (int) $order['user_id'] !== $userId) {
                throw new RuntimeException('Pedido não encontrado.');
            }

            if (in_array((string) ($order['status'] ?? ''), ['queued', 'under_human_review', 'ready'], true)) {
                throw new RuntimeException('Pedido já se encontra além da fase de pagamento.');
            }

            $existingPayment = $this->payments->findOpenByOrderIdForUpdate($orderId);
            if ($existingPayment !== null) {
                $db->commit();
                return [
                    'invoice_id' => (int) $existingPayment['invoice_id'],
                    'payment' => [
                        'payment_id' => (int) $existingPayment['id'],
                        'internal_reference' => (string) ($existingPayment['internal_reference'] ?? ''),
                        'debito_reference' => (string) ($existingPayment['external_reference'] ?? ''),
                        'status' => (string) ($existingPayment['status'] ?? 'pending'),
                        'provider_status' => (string) ($existingPayment['provider_status'] ?? 'PENDING'),
                        'reused_pending_payment' => true,
                    ],
                    'order' => $order,
                ];
            }

            $amount = (float) ($order['final_price'] ?? 0);
            if ($amount <= 0) {
                throw new RuntimeException('Pedido sem valor final para cobrança.');
            }

            $currency = (string) Env::get('DEBITO_CURRENCY', 'MZN');
            $existingInvoice = $this->invoices->findOpenByOrderIdForUpdate($orderId);
            $invoiceId = $existingInvoice !== null
                ? (int) $existingInvoice['id']
                : $this->invoiceService->create((int) $order['user_id'], $orderId, $amount, $currency);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $payment = $this->paymentService->initiateMpesa([
            'user_id' => $userId,
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => $currency,
            'msisdn' => $msisdn,
            'callback_url' => $callbackUrl,
            'internal_notes' => $internalNotes,
        ]);

        return ['invoice_id' => $invoiceId, 'payment' => $payment, 'order' => $order];
    }
}
