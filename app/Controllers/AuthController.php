<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;

final class AuthController extends BaseController
{
    public function showLogin(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        if (!$this->requireCsrfToken()) {
            return;
        }

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errorResponse('Email válido e senha são obrigatórios.', 422, '/login');
            return;
        }

        $user = (new UserRepository())->findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $this->errorResponse('Credenciais inválidas.', 401, '/login');
            return;
        }

        if (!(bool) $user['is_active']) {
            $this->errorResponse('Utilizador inativo. Contacte o suporte.', 403, '/login');
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = (int) $user['id'];
        $_SESSION['auth_user_email'] = (string) $user['email'];

        (new AuditLogRepository())->log((int) $user['id'], 'auth.login', 'user', (int) $user['id'], ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        $this->successResponse('Login efectuado.', '/dashboard', ['user_id' => (int) $user['id']]);
    }

    public function showRegister(): void
    {
        $this->view('auth/register');
    }

    public function register(): void
    {
        if (!$this->requireCsrfToken()) {
            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || $email === '' || strlen($password) < 8 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errorResponse('Dados inválidos. Nome, email válido e senha (mínimo 8) são obrigatórios.', 422, '/register');
            return;
        }

        $users = new UserRepository();
        if ($users->findByEmail($email) !== null) {
            $this->errorResponse('Este email já está registado.', 409, '/register');
            return;
        }

        $userId = $users->create([
            'name' => $name,
            'email' => $email,
            'phone' => $_POST['phone'] ?? null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'institution_id' => !empty($_POST['institution_id']) ? (int) $_POST['institution_id'] : null,
            'course_id' => !empty($_POST['course_id']) ? (int) $_POST['course_id'] : null,
            'discipline_id' => !empty($_POST['discipline_id']) ? (int) $_POST['discipline_id'] : null,
        ]);

        (new AuditLogRepository())->log($userId, 'auth.register', 'user', $userId);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = $userId;
        $_SESSION['auth_user_email'] = $email;

        $this->successResponse('Conta criada com sucesso.', '/dashboard', ['user_id' => $userId], 201);
    }

    public function logout(): void
    {
        if (!$this->requireCsrfToken()) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        if ($userId > 0) {
            (new AuditLogRepository())->log($userId, 'auth.logout', 'user', $userId);
        }

        $this->successResponse('Sessão terminada.', '/login');
    }
}
