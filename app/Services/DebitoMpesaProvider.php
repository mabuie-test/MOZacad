<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class DebitoMpesaProvider implements PaymentProviderInterface
{
    public function __construct(private readonly DebitoClient $client = new DebitoClient()) {}

    public function initiate(array $payload): array
    {
        $wallet = Env::get('DEBITO_WALLET_ID');
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
        $response = $this->client->get("/api/v1/transactions/{$reference}/status");

        return [
            'raw' => $response,
            'provider_status' => (string) ($response['status'] ?? $response['state'] ?? 'PENDING'),
            'provider_message' => (string) ($response['message'] ?? ''),
        ];
    }
}
