<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use RuntimeException;

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
        $normalizedMsisdn = trim($msisdn);
        if ($normalizedMsisdn === '') {
            throw new RuntimeException('Número M-Pesa é obrigatório.');
        }

        try {
            $result = $this->flow->initiateOrderPayment($orderId, $userId, $normalizedMsisdn, $callbackUrl, $internalNotes);
        } catch (RuntimeException $e) {
            $this->logger->info('payment.initiate.validation_failed', ['order_id' => $orderId, 'user_id' => $userId, 'message' => $e->getMessage()]);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('payment.initiate.gateway_failed', ['order_id' => $orderId, 'user_id' => $userId, 'message' => $e->getMessage()]);
            throw new RuntimeException('Gateway de pagamento indisponível. Tente novamente em instantes.');
        }

        $this->logger->info('payment.initiated', [
            'order_id' => $orderId,
            'user_id' => $userId,
            'invoice_id' => (int) $result['invoice_id'],
            'payment_id' => (int) ($result['payment']['payment_id'] ?? $result['payment']['id'] ?? 0),
            'payment_status' => (string) ($result['payment']['status'] ?? 'pending'),
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
