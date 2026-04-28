<?php

declare(strict_types=1);

namespace App\Services;

final class AdminWorkerActionService
{
    /**
     * @return array{checked:int,processed:int,completed:int,failed:int,skipped:int,retried:int}
     */
    public function processAiQueueNow(): array
    {
        return (new AIJobProcessingService())->runBatch();
    }
}
