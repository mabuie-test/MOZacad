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
        private readonly DebitoAuthService $authService = new DebitoAuthService(),
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
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($auth) {
            $headers['Authorization'] = 'Bearer ' . $this->authService->bearerToken();
        }

        $options = [
            'headers' => $headers,
            'http_errors' => false,
        ];

        if ($method === 'POST') {
            $options['json'] = $payload;
        }

        $requestLogPayload = $method === 'POST' ? $payload : [];
        $this->logger->info('Débito request', ['method' => $method, 'uri' => $uri, 'payload' => $requestLogPayload]);

        try {
            $response = $this->http->request($method, ltrim($uri, '/'), $options);
        } catch (GuzzleException $e) {
            $this->logger->error('Erro na comunicação com Débito', [
                'uri' => $uri,
                'method' => $method,
                'exception' => $e->getMessage(),
            ]);
            throw new RuntimeException('Falha ao comunicar com gateway Débito: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true);

        if ($status < 200 || $status >= 300) {
            $providerMessage = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error']['message'] ?? 'erro desconhecido')
                : 'erro desconhecido';

            $this->logger->error('Débito retornou erro HTTP', [
                'method' => $method,
                'uri' => $uri,
                'status' => $status,
                'response' => $decoded,
            ]);

            throw new RuntimeException(sprintf('Débito retornou HTTP %d para %s %s: %s', $status, $method, $uri, $providerMessage));
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida do gateway Débito (JSON malformado).');
        }

        $this->logger->info('Débito response', ['method' => $method, 'uri' => $uri, 'response' => $decoded]);

        return $decoded;
    }
}
