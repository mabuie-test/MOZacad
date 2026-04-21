<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use App\Repositories\InvoiceRepository;

final class InvoiceService
{
    public function __construct(private readonly InvoiceRepository $invoices = new InvoiceRepository()) {}

    public function create(int $userId, int $orderId, float $amount, string $currency = 'MZN'): int
    {
        $openInvoice = $this->invoices->findOpenByOrderId($orderId);
        if ($openInvoice !== null) {
            return (int) $openInvoice['id'];
        }

        $reference = Env::get('INVOICE_PREFIX', 'MZA') . '-' . date('YmdHis') . '-' . random_int(100, 999);
        $effectiveCurrency = trim($currency) !== '' ? $currency : (string) Env::get('PRICING_CURRENCY', 'MZN');

        return $this->invoices->create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'invoice_number' => $reference,
            'amount' => round($amount, 2),
            'currency' => $effectiveCurrency,
            'status' => 'issued',
        ]);
    }
}
