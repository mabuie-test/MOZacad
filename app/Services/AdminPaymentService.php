<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PaymentRepository;
use RuntimeException;

final class AdminPaymentService
{
    public function __construct(
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly PaymentStateTransitionService $transitions = new PaymentStateTransitionService(),
    ) {}

    public function confirmPaymentManually(int $paymentId, string $providerStatus = 'SUCCESSFUL', ?string $note = null): array
    {
        $payment = $this->payments->findById($paymentId);
        if ($payment === null) {
            throw new RuntimeException('Pagamento não encontrado.');
        }

        if ((string) ($payment['status'] ?? '') === 'paid') {
            return $payment;
        }

        $providerStatus = trim($providerStatus);
        if ($providerStatus === '') {
            $providerStatus = 'SUCCESSFUL';
        }

        $reference = trim((string) ($payment['external_reference'] ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($payment['internal_reference'] ?? 'manual:' . $paymentId));
        }

        $payload = [
            'status' => 'SUCCESSFUL',
            'message' => $note ?: 'Pagamento confirmado manualmente no painel administrativo.',
            'source' => 'admin_manual_confirmation',
            'confirmed_at' => date('Y-m-d H:i:s'),
        ];

        $this->transitions->apply($payment, $reference, 'paid', $providerStatus, $payload, 'admin_manual');

        return $this->payments->findById($paymentId) ?? $payment;
    }
}

