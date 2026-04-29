<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class DebitoMpesaProvider implements PaymentProviderInterface
{
    public function __construct(private readonly DebitoClient $client = new DebitoClient()) {}

    public function initiate(array $payload): array
    {
        $idempotencyKey = trim((string) ($payload['source_id'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = bin2hex(random_bytes(16));
        }

        $response = $this->client->post('/payment-orchestrator', $payload, true, [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        if (($response['success'] ?? null) === false) {
            throw new RuntimeException((string) ($response['error'] ?? 'DebitoPay v2 recusou a operação.'));
        }

        $paymentId = $this->extractPaymentId($response);
        if ($paymentId === '') {
            throw new RuntimeException('DebitoPay v2 não retornou payment_id.');
        }

        return [
            'raw' => $response,
            'debito_reference' => $paymentId,
            'provider_status' => $this->extractProviderStatus($response),
            'provider_message' => $this->extractProviderMessage($response),
            'provider_transaction_id' => $this->extractProviderTransactionId($response),
            'provider_code' => $this->extractProviderCode($response),
        ];
    }

    public function checkStatus(string $reference): array
    {
        $paymentId = trim($reference);
        if ($paymentId === '') {
            throw new RuntimeException('Referência DebitoPay payment_id inválida para consulta de status.');
        }

        $response = $this->client->post('/payment-orchestrator', [
            'action' => 'check-status',
            'payment_id' => $paymentId,
        ]);

        if (($response['success'] ?? null) === false) {
            throw new RuntimeException((string) ($response['error'] ?? 'DebitoPay v2 recusou consulta de status.'));
        }

        return [
            'raw' => $response,
            'provider_status' => $this->extractProviderStatus($response),
            'provider_message' => $this->extractProviderMessage($response),
            'provider_code' => $this->extractProviderCode($response),
            'provider_transaction_id' => $this->extractProviderTransactionId($response),
        ];
    }

    private function extractPaymentId(array $response): string
    {
        return trim((string) ($response['payment_id'] ?? $response['payment']['id'] ?? ''));
    }

    private function extractProviderStatus(array $response): string
    {
        return (string) ($response['payment']['status'] ?? $response['status'] ?? 'pending');
    }

    private function extractProviderTransactionId(array $response): string
    {
        return trim((string) ($response['payment']['provider_reference'] ?? $response['payment']['reference'] ?? $response['transactionId'] ?? $response['reference'] ?? ''));
    }

    private function extractProviderMessage(array $response): string
    {
        return (string) ($response['message'] ?? $response['error'] ?? '');
    }

    private function extractProviderCode(array $response): string
    {
        return (string) ($response['error'] ?? $response['code'] ?? '');
    }
}
