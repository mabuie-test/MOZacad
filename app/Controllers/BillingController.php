<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InvoiceRepository;

final class BillingController extends BaseController
{
    public function invoices(): void
    {
        $userId = (int) ($_GET['user_id'] ?? ($_SESSION['auth_user_id'] ?? 0));
        if ($userId <= 0) {
            $this->json(['message' => 'Informe user_id para listar facturas.'], 422);
            return;
        }

        $this->json(['invoices' => (new InvoiceRepository())->listByUser($userId)]);
    }

    public function downloads(): void
    {
        $userId = (int) ($_GET['user_id'] ?? ($_SESSION['auth_user_id'] ?? 0));
        if ($userId <= 0) {
            $this->json(['message' => 'Informe user_id para listar downloads.'], 422);
            return;
        }

        $this->json(['documents' => (new GeneratedDocumentRepository())->listByUser($userId)]);
    }
}
