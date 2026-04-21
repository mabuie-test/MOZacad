<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Jobs\RunPaymentPollingJob;

(new RunPaymentPollingJob())->handle();
echo "Polling executado\n";
