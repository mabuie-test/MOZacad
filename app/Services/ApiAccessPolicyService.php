<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Csrf;

final class ApiAccessPolicyService
{
    /**
     * @param array<string,mixed> $server
     * @param array<string,mixed> $post
     * @return array{allowed:bool,status:int,message:string}
     */
    public function evaluate(array $server, array $post = []): array
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $host = $this->normalizeHost((string) ($server['HTTP_HOST'] ?? ''));
        $originHost = $this->extractHost((string) ($server['HTTP_ORIGIN'] ?? ''));
        $refererHost = $this->extractHost((string) ($server['HTTP_REFERER'] ?? ''));

        if ($host === '') {
            return ['allowed' => false, 'status' => 403, 'message' => 'Host inválido para acesso API.'];
        }

        if ($originHost !== '' || $refererHost !== '') {
            $effective = $originHost !== '' ? $originHost : $refererHost;
            if ($effective !== $host) {
                return ['allowed' => false, 'status' => 403, 'message' => 'Origem não autorizada para esta API.'];
            }
        } else {
            $fetchSite = strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? '')));
            if (!in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
                return ['allowed' => false, 'status' => 403, 'message' => 'A API aceita apenas chamadas first-party.'];
            }
        }

        $client = strtolower(trim((string) ($server['HTTP_X_MOZACAD_CLIENT'] ?? '')));
        if ($client !== 'first-party-web') {
            return ['allowed' => false, 'status' => 403, 'message' => 'Cliente API não autorizado.'];
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = (string) ($server['HTTP_X_CSRF_TOKEN'] ?? ($post['_csrf'] ?? ''));
            if (!Csrf::verify($token)) {
                return ['allowed' => false, 'status' => 419, 'message' => 'Token CSRF inválido para chamada API.'];
            }
        }

        return ['allowed' => true, 'status' => 200, 'message' => 'ok'];
    }

    private function extractHost(string $url): string
    {
        if (trim($url) === '') {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? $this->normalizeHost($host) : '';
    }

    private function normalizeHost(string $host): string
    {
        $normalized = strtolower(trim($host));
        if ($normalized === '') {
            return '';
        }

        return explode(':', $normalized)[0] ?? '';
    }
}
