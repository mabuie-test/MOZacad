<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\InvoiceService;
use App\Services\PaymentService;

final class PaymentController extends BaseController
{
    public function initiateMpesa(): void
    {
        $invoiceId = (new InvoiceService())->create(1, 1, 1500);
        $result = (new PaymentService())->initiateMpesa([
            'user_id' => 1,
            'order_id' => 1,
            'invoice_id' => $invoiceId,
            'amount' => 1500,
            'currency' => 'MZN',
            'msisdn' => $_POST['msisdn'] ?? '841234567',
        ]);

        $this->json($result);
    }

    public function status(): void
    {
        $this->json(['message' => 'Status endpoint pronto']);
    }
}
