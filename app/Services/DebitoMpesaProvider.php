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
        return trim((string) ($this->findFirstScalar($response, [
            'reference',
            'debito_reference',
            'transaction_reference',
            'checkout_reference',
            'request_id',
            'operation_id',
        ]) ?? ''));
    }

    private function extractProviderStatus(array $response): string
    {
        return (string) ($this->findFirstScalar($response, [
            'status',
            'state',
            'transaction_status',
            'payment_status',
        ]) ?? 'PENDING');
    }

    private function extractProviderMessage(array $response): string
    {
        return (string) ($this->findFirstScalar($response, [
            'message',
            'description',
            'details',
            'reason',
        ]) ?? '');
    }

    private function extractProviderTransactionId(array $response): string
    {
        return trim((string) ($this->findFirstScalar($response, [
            'transaction_id',
            'id',
            'provider_transaction_id',
            'checkout_id',
        ]) ?? ''));
    }

    private function findFirstScalar(array $payload, array $preferredKeys): string|int|float|bool|null
    {
        foreach ($preferredKeys as $key) {
            $found = $this->findScalarByKeyRecursive($payload, $key);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function findScalarByKeyRecursive(array $payload, string $key): string|int|float|bool|null
    {
        if (array_key_exists($key, $payload) && is_scalar($payload[$key])) {
            return $payload[$key];
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $found = $this->findScalarByKeyRecursive($value, $key);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

}
