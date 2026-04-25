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
        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        if ($token === '') {
            throw new RuntimeException('DEBITO_TOKEN não configurado para autenticação no gateway Débito.');
        }

        return $token;
    }
}
