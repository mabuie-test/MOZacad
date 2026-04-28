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
        private readonly AuthorizationService $authorization = new AuthorizationService(),
    ) {}

    public function assign(int $actorId, int $queueId, int $reviewerId): void
    {
        if (!$this->authorization->can($actorId, 'human_review.assign')) {
            throw new RuntimeException('Utilizador sem permissão para atribuir revisão humana.');
        }

        $this->queue->assignReviewer($queueId, $reviewerId, $actorId);
        $this->audit->log($actorId, 'admin.human_review.assign', 'human_review_queue', $queueId, ['reviewer_id' => $reviewerId]);
    }

    public function decide(int $actorId, int $queueId, string $decision, ?string $notes, bool $enforceAssignedReviewer = true): void
    {
        if ($decision === 'approve') {
            $this->queue->approve($queueId, $actorId, $notes, $enforceAssignedReviewer);
        } elseif ($decision === 'reject') {
            $this->queue->reject($queueId, $actorId, $notes, $enforceAssignedReviewer);
        } else {
            throw new RuntimeException("decision deve ser 'approve' ou 'reject'.");
        }

        $this->audit->log($actorId, 'admin.human_review.decision', 'human_review_queue', $queueId, ['decision' => $decision, 'enforce_assigned_reviewer' => $enforceAssignedReviewer]);
    }
}
