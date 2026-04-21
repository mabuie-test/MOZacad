<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InvoiceRepository;

final class BillingController extends BaseController
{
    public function invoices(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $this->json(['invoices' => (new InvoiceRepository())->listByUser($userId)]);
    }

    public function downloads(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $this->json(['documents' => (new GeneratedDocumentRepository())->listDeliverableByUser($userId)]);
    }

    private function requireAuthUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return 0;
        }

        return $userId;
    }
}
