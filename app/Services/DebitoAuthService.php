<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class DebitoAuthService
{
    public function __construct(private readonly DebitoClient $client = new DebitoClient()) {}

    public function bearerToken(): string
    {
        $useStatic = filter_var(Env::get('DEBITO_USE_STATIC_TOKEN', true), FILTER_VALIDATE_BOOL);
        $token = (string) Env::get('DEBITO_TOKEN', '');

        if ($useStatic && $token !== '') {
            return $token;
        }

        $response = $this->client->post('/api/v1/login', [
            'email' => Env::get('DEBITO_EMAIL'),
            'password' => Env::get('DEBITO_PASSWORD'),
        ], false);

        return (string)($response['token'] ?? '');
    }
}
