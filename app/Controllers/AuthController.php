<?php

declare(strict_types=1);

namespace App\Controllers;

final class AuthController extends BaseController
{
    public function showLogin(): void { $this->view('auth/login'); }
    public function login(): void { $this->json(['message' => 'Login endpoint pronto']); }
    public function showRegister(): void { $this->view('auth/register'); }
    public function register(): void { $this->json(['message' => 'Register endpoint pronto']); }
    public function logout(): void { $this->json(['message' => 'Logout']); }
}
