<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InvoiceRepository;
use App\Services\DocumentDownloadService;
use App\Services\InvoicePdfService;
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

    public function showInvoice(int $invoiceId): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $invoice = (new InvoiceRepository())->findDetailedById($invoiceId);
        if (!is_array($invoice) || (int) ($invoice['user_id'] ?? 0) !== $userId) {
            $this->errorResponse('Factura não encontrada.', 404, '/invoices');
            return;
        }

        if ($this->isHtmlRequest()) {
            $this->view('billing/invoice_show', ['invoice' => $invoice]);
            return;
        }
        $this->json(['invoice' => $invoice]);
    }

    public function downloadInvoicePdf(int $invoiceId): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $invoice = (new InvoiceRepository())->findDetailedById($invoiceId);
        if (!is_array($invoice) || (int) ($invoice['user_id'] ?? 0) !== $userId) {
            $this->errorResponse('Factura não encontrada.', 404, '/invoices');
            return;
        }

        $pdf = (new InvoicePdfService())->render(
            $invoice,
            ['id' => (int) ($invoice['order_id'] ?? 0), 'title_or_theme' => (string) ($invoice['title_or_theme'] ?? '-'), 'work_type_name' => (string) ($invoice['work_type_name'] ?? '-')],
            ['status' => (string) ($invoice['payment_status'] ?? '-'), 'method' => (string) ($invoice['payment_method'] ?? '-'), 'internal_reference' => (string) ($invoice['internal_reference'] ?? '-')]
        );
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="factura-' . (int) $invoiceId . '.pdf"');
        echo $pdf;
        exit;
    }

    public function adminShowInvoice(int $invoiceId): void
    {
        if (!$this->requireAdminPermission('admin.payments.view', '/admin/payments')) return;
        $invoice = (new InvoiceRepository())->findDetailedById($invoiceId);
        if (!is_array($invoice)) {
            $this->adminError('Factura não encontrada.', 404, '/admin/payments');
            return;
        }
        $this->view('billing/invoice_show', ['invoice' => $invoice, 'isAdminInvoiceView' => true]);
    }

    public function adminDownloadInvoicePdf(int $invoiceId): void
    {
        if (!$this->requireAdminPermission('admin.payments.view', '/admin/payments')) return;
        $invoice = (new InvoiceRepository())->findDetailedById($invoiceId);
        if (!is_array($invoice)) {
            $this->adminError('Factura não encontrada.', 404, '/admin/payments');
            return;
        }

        $pdf = (new InvoicePdfService())->render(
            $invoice,
            ['id' => (int) ($invoice['order_id'] ?? 0), 'title_or_theme' => (string) ($invoice['title_or_theme'] ?? '-'), 'work_type_name' => (string) ($invoice['work_type_name'] ?? '-')],
            ['status' => (string) ($invoice['payment_status'] ?? '-'), 'method' => (string) ($invoice['payment_method'] ?? '-'), 'internal_reference' => (string) ($invoice['internal_reference'] ?? '-')]
        );
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="factura-' . (int) $invoiceId . '.pdf"');
        echo $pdf;
        exit;
    }
}
