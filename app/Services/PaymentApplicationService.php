<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use RuntimeException;
use Throwable;

final class PaymentApplicationService
{
    public function __construct(
        private readonly OrderPaymentFlowService $flow = new OrderPaymentFlowService(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly PaymentProviderInterface $provider = new DebitoMpesaProvider(),
        private readonly DebitoStatusMapper $mapper = new DebitoStatusMapper(),
        private readonly PaymentStateTransitionService $transitions = new PaymentStateTransitionService(),
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

    public function refreshUserPaymentStatus(int $paymentId, int $userId): ?array
    {
        $payment = $this->payments->findById($paymentId);
        if ($payment === null || (int) $payment['user_id'] !== $userId) {
            return null;
        }

        $currentStatus = (string) ($payment['status'] ?? 'pending');
        if (in_array($currentStatus, ['paid', 'failed', 'cancelled', 'expired'], true)) {
            return $payment;
        }

        $externalReference = trim((string) ($payment['external_reference'] ?? ''));
        if ($externalReference === '') {
            return $payment;
        }

        try {
            $statusPayload = $this->provider->checkStatus($externalReference);
            $providerStatus = (string) ($statusPayload['provider_status'] ?? 'PENDING');
            $internalStatus = $this->mapper->map($providerStatus);
            $this->transitions->apply(
                $payment,
                $externalReference,
                $internalStatus,
                $providerStatus,
                $statusPayload['raw'] ?? [],
                'user_status_refresh'
            );
        } catch (Throwable $e) {
            $this->logger->error('payment.refresh.failed', [
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->payments->findById($paymentId) ?? $payment;
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
