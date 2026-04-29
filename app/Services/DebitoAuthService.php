<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use RuntimeException;

final class DebitoAuthService
{
    public function bearerToken(): string
    {
        $token = trim((string) Env::get('DEBITO_TOKEN', ''));
        if ($token === '') {
            throw new RuntimeException('DEBITO_TOKEN não configurado para DebitoPay Payments API v2.');
        }

        return $token;
    }
}
