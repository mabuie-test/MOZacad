<?php

declare(strict_types=1);

use App\Services\ReconcileSuccessfulPaymentsService;

require_once __DIR__ . '/../bootstrap/app.php';

$summary = (new ReconcileSuccessfulPaymentsService())->run();

echo sprintf(
    "Reconcile executado | checked=%d reconciled=%d jobs_created=%d skipped=%d errors=%d\n",
    (int) ($summary['checked'] ?? 0),
    (int) ($summary['reconciled'] ?? 0),
    (int) ($summary['jobs_created'] ?? 0),
    (int) ($summary['skipped'] ?? 0),
    (int) ($summary['errors'] ?? 0)
);
