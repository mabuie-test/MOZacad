<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class DebitoAuthService
{
    private ?string $cachedToken = null;
    private int $tokenExpiresAt = 0;

    public function bearerToken(): string
    {
        $token = trim((string) Env::get('DEBITO_TOKEN', ''));
        if ($token !== '') {
            return $token;
        }

        $useFallback = filter_var((string) Env::get('DEBITO_USE_LOGIN_FALLBACK', false), FILTER_VALIDATE_BOOL);
        if (!$useFallback) {
            throw new RuntimeException('DEBITO_TOKEN não configurado para autenticação no gateway Débito.');
        }

        if ($this->cachedToken !== null && time() < $this->tokenExpiresAt) {
            return $this->cachedToken;
        }

        $this->cachedToken = $this->requestTokenFromLogin();

        return $this->cachedToken;
    }

    private function requestTokenFromLogin(): string
    {
        $username = trim((string) Env::get('DEBITO_LOGIN_USERNAME', ''));
        $password = trim((string) Env::get('DEBITO_LOGIN_PASSWORD', ''));
        if ($username === '' || $password === '') {
            throw new RuntimeException('Fallback de login Débito ativo, mas credenciais não configuradas.');
        }

        $endpoint = ltrim((string) Env::get('DEBITO_LOGIN_ENDPOINT', '/api/v1/login'), '/');
        $tokenPath = trim((string) Env::get('DEBITO_LOGIN_TOKEN_PATH', 'token'));

        $client = new Client([
            'base_uri' => rtrim((string) Env::get('DEBITO_BASE_URL', 'http://localhost:9000'), '/') . '/',
            'timeout' => (int) Env::get('DEBITO_TIMEOUT', 30),
        ]);

        try {
            $response = $client->post($endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'username' => $username,
                    'password' => $password,
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Falha no fallback de autenticação Débito: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Fallback de login Débito falhou com HTTP ' . $status . '.');
        }

        $token = trim((string) $this->extractByPath($decoded, $tokenPath));
        if ($token === '') {
            throw new RuntimeException('Fallback de login Débito não retornou token válido.');
        }

        $expiresIn = max(60, (int) ($decoded['expires_in'] ?? 3600));
        $this->tokenExpiresAt = time() + $expiresIn - 30;

        return $token;
    }

    private function extractByPath(array $payload, string $path): mixed
    {
        $cursor = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
