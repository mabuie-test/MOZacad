<?php

declare(strict_types=1);

namespace App\Services;

final class InvoicePdfService
{
    public function render(array $invoice, array $order, array $payment): string
    {
        $lines = [
            'FACTURA ' . (string) ($invoice['invoice_number'] ?? ''),
            'Pedido #' . (int) ($order['id'] ?? 0),
            'Tema: ' . (string) ($order['title_or_theme'] ?? '-'),
            'Tipo: ' . (string) ($order['work_type_name'] ?? '-'),
            'Valor: ' . number_format((float) ($invoice['amount'] ?? 0), 2, '.', ',') . ' ' . (string) ($invoice['currency'] ?? 'MZN'),
            'Status factura: ' . (string) ($invoice['status'] ?? '-'),
            'Status pagamento: ' . (string) ($payment['status'] ?? '-'),
            'Método: ' . (string) ($payment['method'] ?? '-'),
            'Referência: ' . (string) ($payment['internal_reference'] ?? '-'),
            'Emitida em: ' . (string) ($invoice['issued_at'] ?? $invoice['created_at'] ?? '-'),
            'Pago em: ' . (string) ($invoice['paid_at'] ?? '-'),
        ];

        $content = "BT\n/F1 12 Tf\n50 790 Td\n";
        foreach ($lines as $idx => $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            if ($idx > 0) {
                $content .= "0 -20 Td\n";
            }
            $content .= '(' . $escaped . ") Tj\n";
        }
        $content .= "ET\n";

        $objects = [];
        $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
        $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj";
        $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj";
        $objects[] = "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";
        $objects[] = "5 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "endstream endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}

