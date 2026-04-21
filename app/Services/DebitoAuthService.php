<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use RuntimeException;

final class DebitoAuthService
{
    public function __construct(private readonly DebitoClient $client = new DebitoClient()) {}

    public function bearerToken(): string
    {
        $token = trim((string) Env::get('DEBITO_TOKEN', ''));
        if ($token !== '') {
            return $token;
        }

        $email = (string) Env::get('DEBITO_EMAIL', '');
        $password = (string) Env::get('DEBITO_PASSWORD', '');
        if ($email === '' || $password === '') {
            throw new RuntimeException('Débito sem token estático e sem credenciais de fallback.');
        }

        $response = $this->client->post('/api/v1/login', [
            'email' => $email,
            'password' => $password,
        ], false);

        $dynamicToken = (string) ($response['token'] ?? '');
        if ($dynamicToken === '') {
            throw new RuntimeException('Não foi possível obter token dinâmico do Débito.');
        }

        return $dynamicToken;
    }
}
