<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;

final class PaymentApplicationService
{
    public function __construct(
        private readonly OrderPaymentFlowService $flow = new OrderPaymentFlowService(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
    ) {}

    public function initiateOrderMpesa(int $orderId, int $userId, string $msisdn, ?string $callbackUrl = null, ?string $internalNotes = null): array
    {
        $result = $this->flow->initiateOrderPayment($orderId, $userId, $msisdn, $callbackUrl, $internalNotes);
        $this->logger->info('payment.initiated', [
            'order_id' => $orderId,
            'user_id' => $userId,
            'invoice_id' => (int) $result['invoice_id'],
            'payment_id' => (int) ($result['payment']['id'] ?? 0),
        ]);

        return $result;
    }

    public function userPaymentStatus(int $paymentId, int $userId): ?array
    {
        $payment = $this->payments->findById($paymentId);
        if ($payment === null || (int) $payment['user_id'] !== $userId) {
            return null;
        }

        return $payment;
    }

    public function userOrderById(int $orderId, int $userId): ?array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            return null;
        }

        return $order;
    }
}
