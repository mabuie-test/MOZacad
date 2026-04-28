<?php

declare(strict_types=1);

namespace App\Repositories;

final class HumanReviewDecisionRepository extends BaseRepository
{
    public function create(int $queueId, int $actorId, string $stage, string $decision, ?string $justification = null): void
    {
        $stmt = $this->db->prepare('INSERT INTO human_review_decisions (human_review_queue_id, actor_id, stage, decision, justification, decided_at, created_at) VALUES (:queue_id, :actor_id, :stage, :decision, :justification, NOW(), NOW())');
        $stmt->execute([
            'queue_id' => $queueId,
            'actor_id' => $actorId,
            'stage' => $stage,
            'decision' => $decision,
            'justification' => $justification,
        ]);
    }
}
