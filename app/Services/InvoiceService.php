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
        $reference = Env::get('INVOICE_PREFIX', 'MZA') . '-' . date('YmdHis') . '-' . random_int(100, 999);

        return $this->invoices->create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'invoice_number' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'issued',
        ]);
    }
}
