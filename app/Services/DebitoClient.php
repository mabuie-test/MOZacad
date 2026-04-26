<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class DebitoClient
{
    private Client $http;

    public function __construct(
        private readonly DebitoAuthService $authService = new DebitoAuthService(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {
        $timeout = (int) Env::get('DEBITO_TIMEOUT', 30);
        $this->http = new Client([
            'base_uri' => rtrim((string) Env::get('DEBITO_BASE_URL', 'http://localhost:9000'), '/') . '/',
            'timeout' => max(3, $timeout),
            'connect_timeout' => min(10, max(2, (int) floor($timeout / 2))),
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
        $retries = max(0, (int) Env::get('DEBITO_HTTP_RETRIES', 2));
        $backoffMs = max(100, (int) Env::get('DEBITO_HTTP_BACKOFF_MS', 500));

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Request-ID' => $requestId,
            'User-Agent' => 'MOZacad-DebitoClient/1.1',
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
            'payload_summary' => $method === 'POST' ? $this->summarizePayload($payload) : [],
        ]);

        $attempt = 0;
        start:
        $attempt++;

        try {
            $response = $this->http->request($method, ltrim($uri, '/'), $options);
        } catch (ConnectException|GuzzleException $e) {
            if ($attempt <= ($retries + 1) && $this->isTransientException($e)) {
                usleep(($backoffMs * $attempt) * 1000);
                goto start;
            }

            $this->logger->error('Erro na comunicação com Débito', [
                'request_id' => $requestId,
                'uri' => $uri,
                'method' => $method,
                'attempt' => $attempt,
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
                'non_json_response' => true,
            ];
        }

        if ($status >= 500 && $status < 600 && $attempt <= ($retries + 1)) {
            usleep(($backoffMs * $attempt) * 1000);
            goto start;
        }

        if ($status < 200 || $status >= 300) {
            $providerMessage = $this->extractProviderErrorMessage($decoded);

            $this->logger->error('Débito retornou erro HTTP', [
                'request_id' => $requestId,
                'method' => $method,
                'uri' => $uri,
                'status' => $status,
                'attempt' => $attempt,
                'response_summary' => $this->summarizeResponse($decoded),
            ]);

            throw new RuntimeException(sprintf('Débito retornou HTTP %d para %s %s: %s', $status, $method, $uri, $providerMessage));
        }

        $this->logger->info('Débito response', [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'attempt' => $attempt,
            'response_summary' => $this->summarizeResponse($decoded),
        ]);

        return $decoded;
    }


    /**
     * @return array<string,mixed>
     */
    private function summarizePayload(array $payload): array
    {
        return [
            'keys' => array_keys($payload),
            'reference' => $this->clip((string) ($payload['reference'] ?? $payload['external_reference'] ?? '')),
            'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
            'currency' => (string) ($payload['currency'] ?? ''),
            'msisdn_masked' => $this->maskMsisdn((string) ($payload['msisdn'] ?? $payload['phone'] ?? '')),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function summarizeResponse(array $response): array
    {
        return [
            'keys' => array_keys($response),
            'status' => $response['status'] ?? $response['transaction_status'] ?? null,
            'reference' => $this->clip((string) ($response['reference'] ?? $response['debito_reference'] ?? '')),
            'message' => $this->clip((string) ($response['message'] ?? $response['error']['message'] ?? '')),
        ];
    }

    private function maskMsisdn(string $msisdn): ?string
    {
        $digits = preg_replace('/\D+/', '', $msisdn) ?? '';
        if ($digits === '') {
            return null;
        }

        return mb_substr($digits, 0, 2) . '*****' . mb_substr($digits, -2);
    }

    private function clip(string $value, int $max = 120): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_strlen($trimmed) <= $max ? $trimmed : (mb_substr($trimmed, 0, $max) . '…');
    }

    private function isTransientException(GuzzleException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'timed out')
            || str_contains($message, 'connection')
            || str_contains($message, 'temporar')
            || str_contains($message, 'reset');
    }

    private function extractProviderErrorMessage(array $decoded): string
    {
        $baseMessage = trim((string) ($decoded['message'] ?? $decoded['error']['message'] ?? $decoded['raw_body'] ?? ''));

        if (!isset($decoded['errors']) || !is_array($decoded['errors'])) {
            return $baseMessage !== '' ? $baseMessage : 'erro desconhecido';
        }

        $details = [];
        foreach ($decoded['errors'] as $field => $messages) {
            if (is_array($messages)) {
                foreach ($messages as $item) {
                    if (is_scalar($item) && trim((string) $item) !== '') {
                        $details[] = sprintf('%s: %s', (string) $field, trim((string) $item));
                    }
                }
                continue;
            }

            if (is_scalar($messages) && trim((string) $messages) !== '') {
                $details[] = sprintf('%s: %s', (string) $field, trim((string) $messages));
            }
        }

        if ($details === []) {
            return $baseMessage !== '' ? $baseMessage : 'erro desconhecido';
        }

        return trim($baseMessage . ' (' . implode('; ', $details) . ')');
    }
}
