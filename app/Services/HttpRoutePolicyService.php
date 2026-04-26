<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Csrf;

final class HttpRoutePolicyService
{
    public function enforceAuthenticated(callable $next): mixed
    {
        if ((new AuthContextService())->authenticatedUserId() > 0) {
            return $next();
        }

        $this->deny(401, 'Autenticação obrigatória.', '/login');
        return null;
    }

    public function enforceAdmin(callable $next): mixed
    {
        $adminId = (new AdminAccessService())->currentAdminId();
        if ($adminId > 0) {
            return $next();
        }

        $this->deny($adminId === 0 ? 401 : 403, $adminId === 0 ? 'Autenticação obrigatória.' : 'Sem permissão para este recurso.', $adminId === 0 ? '/login' : '/dashboard');
        return null;
    }

    public function enforceFirstPartyApi(callable $next): mixed
    {
        $result = (new ApiAccessPolicyService())->evaluate($_SERVER, $_POST);
        if (($result['allowed'] ?? false) === true) {
            return $next();
        }

        http_response_code((int) ($result['status'] ?? 403));
        header('Content-Type: application/json');
        echo json_encode(['message' => (string) ($result['message'] ?? 'Acesso API negado.')], JSON_UNESCAPED_UNICODE);
        return null;
    }

    public function enforceCsrfForMutations(callable $next): mixed
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next();
        }

        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($path, '/api/')) {
            return $next();
        }

        $token = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (Csrf::verify($token)) {
            return $next();
        }

        $this->deny(419, 'Sessão expirada. Atualize a página e tente novamente.', '/');
        return null;
    }

    private function deny(int $status, string $message, string $htmlRedirect): void
    {
        http_response_code($status);
        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Location: ' . $htmlRedirect);
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return str_starts_with($path, '/api/') || $xrw === 'xmlhttprequest' || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));
    }
}
