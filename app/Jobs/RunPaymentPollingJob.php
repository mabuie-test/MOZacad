<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PaymentStatusPollingService;

final class RunPaymentPollingJob
{
    /**
     * @return array{checked:int,updated:int,paid:int,errors:int}
     */
    public function handle(): array
    {
        return (new PaymentStatusPollingService())->run();
    }
}
