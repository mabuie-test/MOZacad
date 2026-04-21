<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\HumanReviewQueueRepository;

final class HumanReviewQueueService
{
    public function __construct(private readonly HumanReviewQueueRepository $queue = new HumanReviewQueueRepository()) {}

    public function enqueue(int $orderId, ?int $reviewerId = null): int
    {
        return $this->queue->enqueue($orderId, $reviewerId);
    }

    public function assignReviewer(int $queueId, int $reviewerId): void
    {
        $this->queue->assignReviewer($queueId, $reviewerId);
    }

    public function approve(int $queueId, ?string $notes = null): void
    {
        $this->queue->updateDecision($queueId, 'approved', $notes);
    }

    public function reject(int $queueId, ?string $notes = null): void
    {
        $this->queue->updateDecision($queueId, 'rejected', $notes);
    }
}
