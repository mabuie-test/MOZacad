<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use InvalidArgumentException;

final class MpesaMsisdnValidator
{
    public function sanitize(string $msisdn): string
    {
        $sanitized = preg_replace('/\D+/', '', $msisdn) ?? '';
        $countryCode = preg_replace('/\D+/', '', (string) Env::get('MPESA_COUNTRY_CODE', '258'));

        if ($countryCode === 'MZ' || $countryCode === 'mz') {
            $countryCode = '258';
        }

        if ($countryCode !== '' && str_starts_with($sanitized, $countryCode)) {
            $sanitized = substr($sanitized, strlen($countryCode));
        }

        return $sanitized;
    }

    public function validate(string $msisdn): string
    {
        $value = $this->sanitize($msisdn);
        $regex = (string) Env::get('MPESA_MSISDN_REGEX', '/^(84|85)\d{7}$/');

        if (!preg_match($regex, $value)) {
            throw new InvalidArgumentException('Número M-Pesa inválido. Use 84xxxxxxx ou 85xxxxxxx.');
        }

        return $value;
    }
}
