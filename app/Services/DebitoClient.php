<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class DebitoClient
{
    private Client $http;

    public function __construct(
        private readonly ?DebitoAuthService $authService = null,
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {
        $this->http = new Client([
            'base_uri' => rtrim((string) Env::get('DEBITO_BASE_URL', 'http://localhost:9000'), '/') . '/',
            'timeout' => (int) Env::get('DEBITO_TIMEOUT', 30),
        ]);
    }

    public function post(string $uri, array $payload, bool $auth = true): array
    {
        return $this->request('POST', $uri, $payload, $auth);
    }

    public function get(string $uri, bool $auth = true): array
    {
        return $this->request('GET', $uri, [], $auth);
    }

    private function request(string $method, string $uri, array $payload = [], bool $auth = true): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($auth) {
            $token = ($this->authService ?? new DebitoAuthService($this))->bearerToken();
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $options = ['headers' => $headers];
        if ($method === 'POST') {
            $options['json'] = $payload;
        }

        $this->logger->info('Débito request', ['method' => $method, 'uri' => $uri, 'payload' => $payload]);

        try {
            $response = $this->http->request($method, ltrim($uri, '/'), $options + ['http_errors' => false]);
        } catch (GuzzleException $e) {
            $this->logger->error('Erro na comunicação com Débito', ['uri' => $uri, 'method' => $method, 'exception' => $e->getMessage()]);
            throw new RuntimeException('Falha ao comunicar com gateway Débito: ' . $e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException(sprintf('Débito retornou HTTP %d para %s %s', $response->getStatusCode(), $method, $uri));
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida do gateway Débito.');
        }
        $this->logger->info('Débito response', ['method' => $method, 'uri' => $uri, 'response' => $decoded]);

        return $decoded;
    }
}
