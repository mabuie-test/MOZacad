<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PaymentStatusPollingService;

final class RunPaymentPollingJob
{
    public function handle(): void
    {
        (new PaymentStatusPollingService())->run();
    }
}
