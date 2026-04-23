<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InvoiceRepository;
use App\Services\DocumentDownloadService;
use RuntimeException;

final class BillingController extends BaseController
{
    public function invoices(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $invoices = (new InvoiceRepository())->listByUser($userId);
        if ($this->isHtmlRequest()) {
            $this->view('billing/invoices', ['invoices' => $invoices]);
            return;
        }

        $this->json(['invoices' => $invoices]);
    }

    public function downloads(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $documents = (new GeneratedDocumentRepository())->listDeliverableByUser($userId);
        if ($this->isHtmlRequest()) {
            $this->view('billing/downloads', ['documents' => $documents]);
            return;
        }

        $this->json(['documents' => $documents]);
    }

    public function downloadDocument(int $documentId): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        try {
            $file = (new DocumentDownloadService())->resolve($documentId, $userId);
            header('Content-Type: ' . $file['mime']);
            header('Content-Disposition: attachment; filename="' . basename($file['download_name']) . '"');
            header('Content-Length: ' . (string) filesize($file['path']));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            readfile($file['path']);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 403);
        }
    }
}
