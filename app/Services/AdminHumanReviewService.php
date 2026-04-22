<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use RuntimeException;

final class AdminHumanReviewService
{
    public function __construct(
        private readonly HumanReviewQueueService $queue = new HumanReviewQueueService(),
        private readonly AuditLogRepository $audit = new AuditLogRepository(),
    ) {}

    public function assign(int $actorId, int $queueId, int $reviewerId): void
    {
        $this->queue->assignReviewer($queueId, $reviewerId);
        $this->audit->log($actorId, 'admin.human_review.assign', 'human_review_queue', $queueId, ['reviewer_id' => $reviewerId]);
    }

    public function decide(int $actorId, int $queueId, string $decision, ?string $notes): void
    {
        if ($decision === 'approve') {
            $this->queue->approve($queueId, $notes);
        } elseif ($decision === 'reject') {
            $this->queue->reject($queueId, $notes);
        } else {
            throw new RuntimeException("decision deve ser 'approve' ou 'reject'.");
        }

        $this->audit->log($actorId, 'admin.human_review.decision', 'human_review_queue', $queueId, ['decision' => $decision]);
    }
}
