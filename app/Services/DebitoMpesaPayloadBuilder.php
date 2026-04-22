<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use InvalidArgumentException;

final class DebitoMpesaPayloadBuilder
{
    public function __construct(private readonly MpesaMsisdnValidator $validator = new MpesaMsisdnValidator()) {}

    public function build(
        float $amount,
        string $msisdn,
        string $referenceDescription,
        ?string $callbackUrl = null,
        ?string $internalNotes = null
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Montante de pagamento inválido para M-Pesa C2B.');
        }

        $payload = [
            'msisdn' => $this->validator->validate($msisdn),
            'amount' => round($amount, 2),
            'reference_description' => trim($referenceDescription),
        ];

        $effectiveCallbackUrl = $this->resolveCallbackUrl($callbackUrl);
        if ($effectiveCallbackUrl !== '') {
            $payload['callback_url'] = $effectiveCallbackUrl;
        }

        if ($internalNotes !== null && trim($internalNotes) !== '') {
            $payload['internal_notes'] = trim($internalNotes);
        }

        return $payload;
    }

    private function resolveCallbackUrl(?string $callbackUrl): string
    {
        $direct = trim((string) ($callbackUrl ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $envCallback = trim((string) Env::get('DEBITO_CALLBACK_URL', ''));
        if ($envCallback !== '') {
            return $envCallback;
        }

        return '';
    }
}
