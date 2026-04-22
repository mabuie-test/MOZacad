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
        $wallet = trim((string) Env::get('DEBITO_WALLET_ID', ''));
        if ($wallet === '') {
            throw new RuntimeException('DEBITO_WALLET_ID não configurado.');
        }

        $response = $this->client->post("/api/v1/wallets/{$wallet}/c2b/mpesa", $payload);

        return [
            'raw' => $response,
            'debito_reference' => $this->extractReference($response),
            'provider_status' => $this->extractProviderStatus($response),
            'provider_message' => $this->extractProviderMessage($response),
            'provider_transaction_id' => $this->extractProviderTransactionId($response),
            'provider_code' => (string) ($response['code'] ?? $response['error']['code'] ?? ''),
        ];
    }

    public function checkStatus(string $reference): array
    {
        $debitoReference = trim($reference);
        if ($debitoReference === '') {
            throw new RuntimeException('Referência Débito inválida para consulta de status.');
        }

        $response = $this->client->get("/api/v1/transactions/{$debitoReference}/status");

        return [
            'raw' => $response,
            'provider_status' => $this->extractProviderStatus($response),
            'provider_message' => $this->extractProviderMessage($response),
            'provider_code' => (string) ($response['code'] ?? $response['error']['code'] ?? ''),
        ];
    }

    private function extractReference(array $response): string
    {
        return trim((string) (
            $response['reference']
            ?? $response['debito_reference']
            ?? $response['transaction_reference']
            ?? $response['data']['reference']
            ?? ''
        ));
    }

    private function extractProviderStatus(array $response): string
    {
        return (string) (
            $response['status']
            ?? $response['state']
            ?? $response['transaction_status']
            ?? $response['data']['status']
            ?? 'PENDING'
        );
    }

    private function extractProviderMessage(array $response): string
    {
        return (string) (
            $response['message']
            ?? $response['description']
            ?? $response['error']['message']
            ?? $response['data']['message']
            ?? ''
        );
    }

    private function extractProviderTransactionId(array $response): string
    {
        return trim((string) (
            $response['transaction_id']
            ?? $response['id']
            ?? $response['data']['transaction_id']
            ?? $response['data']['id']
            ?? ''
        ));
    }

}
