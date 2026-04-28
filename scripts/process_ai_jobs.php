<?php

declare(strict_types=1);

use App\Services\AIJobProcessingService;

require_once __DIR__ . '/../bootstrap/app.php';

$summary = (new AIJobProcessingService())->runBatch();

if (($summary['checked'] ?? 0) === 0) {
    echo "Nenhum AI job pendente.\n";
    exit(0);
}

echo sprintf(
    "AI jobs executado | checked=%d processed=%d completed=%d retried=%d failed=%d skipped=%d\n",
    (int) ($summary['checked'] ?? 0),
    (int) ($summary['processed'] ?? 0),
    (int) ($summary['completed'] ?? 0),
    (int) ($summary['retried'] ?? 0),
    (int) ($summary['failed'] ?? 0),
    (int) ($summary['skipped'] ?? 0),
);
