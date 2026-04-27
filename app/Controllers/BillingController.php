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

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            @ini_set('display_errors', '0');

            $path = (string) $file['path'];
            $name = basename((string) $file['download_name']);
            $size = filesize($path);
            if ($size === false) {
                throw new RuntimeException('Não foi possível ler o ficheiro para download.');
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . (string) $size);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');

            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Falha ao abrir ficheiro para transmissão.');
            }

            fpassthru($handle);
            fclose($handle);
            exit;
        } catch (RuntimeException $e) {
            $this->errorResponse($e->getMessage(), 403, '/downloads');
        }
    }
}
