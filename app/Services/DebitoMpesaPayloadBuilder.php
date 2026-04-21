<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class DebitoMpesaPayloadBuilder
{
    public function __construct(private readonly MpesaMsisdnValidator $validator = new MpesaMsisdnValidator()) {}

    public function build(float $amount, string $msisdn, string $referenceDescription): array
    {
        return [
            'amount' => round($amount, 2),
            'currency' => Env::get('DEBITO_CURRENCY', 'MZN'),
            'msisdn' => $this->validator->validate($msisdn),
            'reference_description' => $referenceDescription,
            'callback_url' => Env::get('DEBITO_CALLBACK_URL') ?: null,
        ];
    }
}
