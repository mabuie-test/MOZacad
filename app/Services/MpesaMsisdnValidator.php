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
        if (str_starts_with($sanitized, '258')) {
            $sanitized = substr($sanitized, 3);
        }
        return $sanitized;
    }

    public function validate(string $msisdn): string
    {
        $value = $this->sanitize($msisdn);
        $regex = Env::get('MPESA_MSISDN_REGEX', '/^(84|85)\d{7}$/');
        if (!preg_match($regex, $value)) {
            throw new InvalidArgumentException('Número M-Pesa inválido. Use 84xxxxxxx ou 85xxxxxxx.');
        }
        return $value;
    }
}
