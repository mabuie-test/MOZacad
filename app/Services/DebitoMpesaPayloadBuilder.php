<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use InvalidArgumentException;

final class DebitoMpesaPayloadBuilder
{
    public function __construct(private readonly MpesaMsisdnValidator $validator = new MpesaMsisdnValidator()) {}

    public function build(float|int|string $amount, string $msisdn, string $referenceDescription, ?string $callbackUrl = null, ?string $internalNotes = null): array
    {
        $normalizedAmount = round($this->normalizeAmount($amount), 2);
        if ($normalizedAmount < 10) {
            throw new InvalidArgumentException('Montante mínimo M-Pesa é 10 MZN.');
        }

        $merchantId = trim((string) Env::get('DEBITO_MERCHANT_ID', ''));
        if ($merchantId === '') {
            throw new InvalidArgumentException('DEBITO_MERCHANT_ID obrigatório para DebitoPay v2.');
        }

        $walletCode = trim((string) Env::get('DEBITO_WALLET_CODE', ''));
        if ($walletCode === '') {
            throw new InvalidArgumentException('DEBITO_WALLET_CODE obrigatório para DebitoPay v2.');
        }

        $currency = strtoupper(trim((string) Env::get('DEBITO_CURRENCY', 'MZN')));
        if ($currency !== 'MZN') {
            throw new InvalidArgumentException('DEBITO_CURRENCY deve ser MZN para DebitoPay v2 M-Pesa.');
        }

        $phone = $this->normalizeMsisdn($msisdn);

        return [
            'action' => 'process',
            'payment_method' => 'mpesa',
            'merchant_id' => $merchantId,
            'wallet_code' => $walletCode,
            'amount' => $normalizedAmount,
            'currency' => $currency,
            'phone' => $phone,
            'source' => 'gateway',
            'source_id' => trim($referenceDescription),
            'customer_phone' => $phone,
        ];
    }

    private function normalizeAmount(float|int|string $amount): float { /* unchanged */
        if (is_int($amount) || is_float($amount)) return (float) $amount;
        $raw = str_replace(' ', '', trim((string) $amount));
        if ($raw === '') throw new InvalidArgumentException('Montante inválido.');
        if (str_contains($raw, ',') && str_contains($raw, '.')) { $raw = str_replace('.', '', $raw); $raw = str_replace(',', '.', $raw); }
        elseif (str_contains($raw, ',')) { $raw = str_replace(',', '.', $raw); }
        if (!is_numeric($raw)) throw new InvalidArgumentException('Montante inválido.');
        return (float) $raw;
    }

    private function normalizeMsisdn(string $msisdn): string
    {
        $input = trim($msisdn);
        if ($input === '') {
            throw new InvalidArgumentException('Telefone M-Pesa obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '258')) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) !== 9 || !preg_match('/^(84|85)\d{7}$/', $digits)) {
            throw new InvalidArgumentException('Número M-Pesa inválido. Use 84xxxxxxx ou 85xxxxxxx.');
        }

        $this->validator->validate($digits);

        return '+258' . $digits;
    }
}
