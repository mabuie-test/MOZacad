<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Jobs\RunPaymentPollingJob;

$summary = (new RunPaymentPollingJob())->handle();

echo sprintf(
    "Polling executado | checked=%d updated=%d paid=%d errors=%d\n",
    $summary['checked'],
    $summary['updated'],
    $summary['paid'],
    $summary['errors']
);
