<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use InvalidArgumentException;

final class DebitoMpesaPayloadBuilder
{
    public function __construct(private readonly MpesaMsisdnValidator $validator = new MpesaMsisdnValidator()) {}

    public function build(
        float|int|string $amount,
        string $msisdn,
        string $referenceDescription,
        ?string $callbackUrl = null,
        ?string $internalNotes = null
    ): array {
        $normalizedAmount = $this->normalizeAmount($amount);
        if ($normalizedAmount <= 0) {
            throw new InvalidArgumentException('Montante de pagamento inválido para M-Pesa C2B.');
        }

        $formattedAmount = number_format(round($normalizedAmount, 2), 2, '.', '');

        $payload = [
            'msisdn' => $this->validator->validate($msisdn),
            'amount' => $formattedAmount,
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

    private function normalizeAmount(float|int|string $amount): float
    {
        if (is_int($amount) || is_float($amount)) {
            return (float) $amount;
        }

        $raw = trim($amount);
        if ($raw === '') {
            throw new InvalidArgumentException('Montante de pagamento inválido para M-Pesa C2B.');
        }

        $raw = str_replace(' ', '', $raw);
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('Montante de pagamento inválido para M-Pesa C2B.');
        }

        return (float) $raw;
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
