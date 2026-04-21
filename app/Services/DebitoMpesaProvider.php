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
        return $this->client->post("/api/v1/wallets/{$wallet}/c2b/mpesa", $payload);
    }

    public function checkStatus(string $reference): array
    {
        return $this->client->get("/api/v1/transactions/{$reference}/status");
    }
}
