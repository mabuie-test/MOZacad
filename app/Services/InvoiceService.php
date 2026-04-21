<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Env;

final class InvoiceService
{
    public function create(int $userId, int $orderId, float $amount, string $currency = 'MZN'): int
    {
        $reference = Env::get('INVOICE_PREFIX', 'MZA') . '-' . date('YmdHis') . '-' . random_int(100, 999);
        $sql = 'INSERT INTO invoices (user_id, order_id, invoice_number, amount, currency, status, issued_at, created_at, updated_at)
                VALUES (:user_id,:order_id,:invoice_number,:amount,:currency,:status,NOW(),NOW(),NOW())';

        $db = Database::connect();
        $db->prepare($sql)->execute([
            'user_id' => $userId,
            'order_id' => $orderId,
            'invoice_number' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'issued',
        ]);

        return (int)$db->lastInsertId();
    }
}
