<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use GuzzleHttp\Client;

final class DebitoClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => Env::get('DEBITO_BASE_URL', 'http://localhost:9000'),
            'timeout' => (int) Env::get('DEBITO_TIMEOUT', 30),
        ]);
    }

    public function post(string $uri, array $payload, bool $auth = true): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($auth) {
            $token = (new DebitoAuthService($this))->bearerToken();
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = $this->http->post($uri, ['json' => $payload, 'headers' => $headers]);
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    public function get(string $uri): array
    {
        $token = (new DebitoAuthService($this))->bearerToken();
        $response = $this->http->get($uri, ['headers' => ['Authorization' => 'Bearer ' . $token]]);
        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
