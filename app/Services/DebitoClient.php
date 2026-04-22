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
        $requestId = bin2hex(random_bytes(8));

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Request-ID' => $requestId,
            'User-Agent' => 'MOZacad-DebitoClient/1.0',
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

        $this->logger->info('Débito request', [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'payload' => $method === 'POST' ? $payload : [],
        ]);

        try {
            $response = $this->http->request($method, ltrim($uri, '/'), $options);
        } catch (GuzzleException $e) {
            $this->logger->error('Erro na comunicação com Débito', [
                'request_id' => $requestId,
                'uri' => $uri,
                'method' => $method,
                'exception' => $e->getMessage(),
            ]);
            throw new RuntimeException('Falha ao comunicar com gateway Débito: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $rawBody = trim((string) $response->getBody());
        $decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;

        if (!is_array($decoded)) {
            $decoded = [
                'status' => $status,
                'raw_body' => mb_substr($rawBody, 0, 2000),
            ];
        }

        if ($status < 200 || $status >= 300) {
            $providerMessage = (string) ($decoded['message'] ?? $decoded['error']['message'] ?? $decoded['raw_body'] ?? 'erro desconhecido');

            $this->logger->error('Débito retornou erro HTTP', [
                'request_id' => $requestId,
                'method' => $method,
                'uri' => $uri,
                'status' => $status,
                'response' => $decoded,
            ]);

            throw new RuntimeException(sprintf('Débito retornou HTTP %d para %s %s: %s', $status, $method, $uri, $providerMessage));
        }

        $this->logger->info('Débito response', [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'response' => $decoded,
        ]);

        return $decoded;
    }
}
