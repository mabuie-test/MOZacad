<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use App\Repositories\OrderRepository;
use RuntimeException;

final class OrderPaymentFlowService
{
    public function initiateOrderPayment(int $orderId, int $userId, string $msisdn, ?string $callbackUrl = null, ?string $internalNotes = null): array
    {
        $order = (new OrderRepository())->findById($orderId);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            throw new RuntimeException('Pedido não encontrado.');
        }

        $amount = (float) ($order['final_price'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('Pedido sem valor final para cobrança.');
        }

        $currency = (string) Env::get('DEBITO_CURRENCY', 'MZN');
        $invoiceId = (new InvoiceService())->create((int) $order['user_id'], $orderId, $amount, $currency);

        $payment = (new PaymentService())->initiateMpesa([
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
