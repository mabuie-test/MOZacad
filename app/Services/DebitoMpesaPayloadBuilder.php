<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class DebitoMpesaPayloadBuilder
{
    public function __construct(private readonly MpesaMsisdnValidator $validator = new MpesaMsisdnValidator()) {}

    public function build(float $amount, string $msisdn, string $referenceDescription, ?string $internalNotes = null): array
    {
        $payload = [
            'amount' => round($amount, 2),
            'currency' => Env::get('DEBITO_CURRENCY', 'MZN'),
            'msisdn' => $this->validator->validate($msisdn),
            'reference_description' => $referenceDescription,
        ];

        if ((string) Env::get('DEBITO_CALLBACK_URL', '') !== '') {
            $payload['callback_url'] = Env::get('DEBITO_CALLBACK_URL');
        }

        if ($internalNotes !== null && trim($internalNotes) !== '') {
            $payload['internal_notes'] = $internalNotes;
        }

        return $payload;
    }
}
