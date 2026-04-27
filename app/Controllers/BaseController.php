<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\AdminAccessService;
use App\Services\ApiAccessPolicyService;
use App\Services\AuthContextService;

abstract class BaseController
{
    protected function view(string $template, array $data = []): void
    {
        $data['csrfToken'] = Csrf::token();
        $data['isAuthenticated'] = $this->authenticatedUserId() > 0;
        $data['isAdmin'] = (new AdminAccessService())->currentAdminId() > 0;
        $data['currentPath'] = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $data['flash'] = $this->pullFlash();
        View::render($template, $data);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    protected function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if (str_starts_with($path, '/api/')) {
            return true;
        }

        if ($xrw === 'xmlhttprequest') {
            return true;
        }

        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }

    protected function isHtmlRequest(): bool
    {
        return !$this->wantsJson();
    }

    protected function successResponse(string $message, string $redirectPath, array $payload = [], int $status = 200): void
    {
        if ($this->isHtmlRequest()) {
            $this->flash('success', $message);
            $this->redirect($redirectPath);
            return;
        }

        $this->json(array_merge(['message' => $message], $payload), $status);
    }

    protected function errorResponse(string $message, int $status, string $redirectPath, array $payload = []): void
    {
        if ($this->isHtmlRequest()) {
            $this->flash($status >= 500 ? 'error' : 'warning', $message);
            $this->redirect($redirectPath);
            return;
        }

        $this->json(array_merge(['message' => $message], $payload), $status);
    }

    protected function internalErrorResponse(string $message = 'Erro interno. Tente novamente em instantes.', string $redirectPath = '/'): void
    {
        $this->errorResponse($message, 500, $redirectPath);
    }

    protected function requireCsrfToken(): bool
    {
        $token = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

        if (!Csrf::verify($token)) {
            $this->errorResponse('Sessão expirada. Atualize a página e tente novamente.', 419, $this->refererPath('/'));
            return false;
        }

        return true;
    }

    protected function requireAuthUserId(): int
    {
        $userId = $this->authenticatedUserId();
        if ($userId <= 0) {
            $this->errorResponse('Autenticação obrigatória.', 401, '/login');
            return 0;
        }

        return $userId;
    }

    protected function requireFirstPartyApiAccess(): bool
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!str_starts_with($path, '/api/')) {
            return true;
        }

        $result = (new ApiAccessPolicyService())->evaluate($_SERVER, $_POST);
        if (($result['allowed'] ?? false) === true) {
            return true;
        }

        $status = (int) ($result['status'] ?? 403);
        $message = (string) ($result['message'] ?? 'Acesso API negado.');
        $this->errorResponse($message, $status, '/dashboard');
        return false;
    }

    protected function requireAdminAccess(): bool
    {
        $adminId = (new AdminAccessService())->currentAdminId();
        if ($adminId === 0) {
            $this->errorResponse('Área restrita. Faça login com conta administrativa.', 401, '/login');
            return false;
        }

        if ($adminId < 0) {
            $this->errorResponse('Sem permissão para recursos administrativos.', 403, '/dashboard');
            return false;
        }

        return true;
    }

    protected function guardAdminPost(): bool
    {
        return $this->requireAdminAccess() && $this->requireCsrfToken();
    }

    protected function adminSuccess(string $message, string $redirectPath, array $payload = []): void
    {
        $this->successResponse($message, $redirectPath, $payload);
    }

    protected function adminError(string $message, int $status, string $redirectPath, array $payload = []): void
    {
        $this->errorResponse($message, $status, $redirectPath, $payload);
    }

    protected function audit(string $action, string $subjectType, ?int $subjectId = null, array $payload = []): void
    {
        (new AuditLogRepository())->log((int) ($_SESSION['auth_user_id'] ?? 0), $action, $subjectType, $subjectId, $payload);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
    }

    protected function flash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    protected function refererPath(string $fallback = '/'): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path = parse_url($referer, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $fallback;
    }

    private function pullFlash(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }

    private function authenticatedUserId(): int
    {
        return (new AuthContextService())->authenticatedUserId();
    }
}
