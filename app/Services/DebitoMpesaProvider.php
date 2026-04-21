<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use RuntimeException;

final class DebitoMpesaProvider implements PaymentProviderInterface
{
    public function __construct(private readonly DebitoClient $client = new DebitoClient()) {}

    public function initiate(array $payload): array
    {
        $this->assertMpesaEnabled();

        $wallet = trim((string) Env::get('DEBITO_WALLET_ID', ''));
        if ($wallet === '') {
            throw new RuntimeException('DEBITO_WALLET_ID não configurado.');
        }

        $response = $this->client->post("/api/v1/wallets/{$wallet}/c2b/mpesa", $payload);

        return [
            'raw' => $response,
            'debito_reference' => (string) ($response['reference'] ?? $response['debito_reference'] ?? $response['data']['reference'] ?? ''),
            'provider_status' => (string) ($response['status'] ?? $response['state'] ?? 'PENDING'),
            'provider_message' => (string) ($response['message'] ?? ''),
            'provider_transaction_id' => (string) ($response['transaction_id'] ?? $response['id'] ?? ''),
            'provider_code' => (string) ($response['code'] ?? ''),
        ];
    }

    public function checkStatus(string $reference): array
    {
        $this->assertMpesaEnabled();

        $debitoReference = trim($reference);
        if ($debitoReference === '') {
            throw new RuntimeException('Referência Débito inválida para consulta de status.');
        }

        $response = $this->client->get("/api/v1/transactions/{$debitoReference}/status");

        return [
            'raw' => $response,
            'provider_status' => (string) ($response['status'] ?? $response['state'] ?? 'PENDING'),
            'provider_message' => (string) ($response['message'] ?? ''),
            'provider_code' => (string) ($response['code'] ?? ''),
        ];
    }

    private function assertMpesaEnabled(): void
    {
        $enabled = filter_var((string) Env::get('DEBITO_MPESA_ENABLED', true), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            throw new RuntimeException('Pagamento M-Pesa está desativado por configuração (DEBITO_MPESA_ENABLED=false).');
        }
    }
}
