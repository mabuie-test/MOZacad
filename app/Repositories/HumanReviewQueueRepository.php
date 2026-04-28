<?php

declare(strict_types=1);

namespace App\Repositories;

final class HumanReviewQueueRepository extends BaseRepository
{
    public function enqueue(int $orderId, int $documentId, int $documentVersion, ?int $reviewerId = null, ?int $createdBy = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO human_review_queue (order_id, generated_document_id, generated_document_version, reviewer_id, created_by, status, approval_count, required_approvals, created_at, updated_at) VALUES (:order_id, :generated_document_id, :generated_document_version, :reviewer_id, :created_by, 'pending', 0, 1, NOW(), NOW())");
        $stmt->execute([
            'order_id' => $orderId,
            'generated_document_id' => $documentId,
            'generated_document_version' => $documentVersion,
            'reviewer_id' => $reviewerId,
            'created_by' => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function assignReviewer(int $queueId, int $reviewerId, int $assignedBy): void
    {
        $stmt = $this->db->prepare("UPDATE human_review_queue SET reviewer_id = :reviewer_id, assigned_by = :assigned_by, status = 'assigned', updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $queueId, 'reviewer_id' => $reviewerId, 'assigned_by' => $assignedBy]);
    }

    public function setRequiredApprovals(int $queueId, int $requiredApprovals): void
    {
        $stmt = $this->db->prepare('UPDATE human_review_queue SET required_approvals = :required_approvals, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $queueId,
            'required_approvals' => max(1, $requiredApprovals),
        ]);
    }

    public function findById(int $queueId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM human_review_queue WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $queueId]);

        return $stmt->fetch() ?: null;
    }

    public function lockByIdForUpdate(int $queueId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM human_review_queue WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $queueId]);

        return $stmt->fetch() ?: null;
    }

    public function findOpenByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM human_review_queue
            WHERE order_id = :order_id
              AND status IN ('pending','assigned','qa_approved')
            ORDER BY id DESC
            LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function findOpenByOrderIdForUpdate(int $orderId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM human_review_queue
            WHERE order_id = :order_id
              AND status IN ('pending','assigned','qa_approved')
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE");
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function markQaApproved(int $queueId, int $actorId, ?string $notes = null): void
    {
        $stmt = $this->db->prepare("UPDATE human_review_queue
            SET status = 'qa_approved', decision = 'approve', comments = :review_notes, approval_count = approval_count + 1, last_decided_by = :actor_id, updated_at = NOW()
            WHERE id = :id
              AND status IN ('pending','assigned')");
        $stmt->execute([
            'id' => $queueId,
            'review_notes' => $notes,
            'actor_id' => $actorId,
        ]);
    }

    public function markFinalApproved(int $queueId, int $actorId, ?string $notes = null): void
    {
        $stmt = $this->db->prepare("UPDATE human_review_queue
            SET status = 'final_approved', decision = 'approve', comments = :review_notes, approval_count = approval_count + 1, last_decided_by = :actor_id, updated_at = NOW()
            WHERE id = :id
              AND status IN ('pending','assigned','qa_approved')");
        $stmt->execute([
            'id' => $queueId,
            'review_notes' => $notes,
            'actor_id' => $actorId,
        ]);
    }

    public function markRejected(int $queueId, int $actorId, ?string $notes = null): void
    {
        $stmt = $this->db->prepare("UPDATE human_review_queue
            SET status = 'rejected', decision = 'reject', comments = :review_notes, last_decided_by = :actor_id, updated_at = NOW()
            WHERE id = :id
              AND status IN ('pending','assigned','qa_approved')");
        $stmt->execute([
            'id' => $queueId,
            'review_notes' => $notes,
            'actor_id' => $actorId,
        ]);
    }

    public function listQueue(int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT q.*, o.title_or_theme, o.status AS order_status, u.email AS user_email
            FROM human_review_queue q
            INNER JOIN orders o ON o.id = q.order_id
            INNER JOIN users u ON u.id = o.user_id
            ORDER BY q.created_at DESC
            LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
