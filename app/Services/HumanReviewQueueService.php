<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\OrderRepository;
use RuntimeException;

final class HumanReviewQueueService
{
    public function __construct(
        private readonly HumanReviewQueueRepository $queue = new HumanReviewQueueRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly GeneratedDocumentRepository $documents = new GeneratedDocumentRepository(),
        private readonly RevisionService $revisions = new RevisionService()
    ) {}

    public function enqueue(int $orderId, ?int $reviewerId = null): int
    {
        return $this->queue->enqueue($orderId, $reviewerId);
    }

    public function assignReviewer(int $queueId, int $reviewerId): void
    {
        $entry = $this->queue->findById($queueId);
        if ($entry === null) {
            throw new RuntimeException('Item de fila de revisão humana não encontrado.');
        }

        if (in_array((string) ($entry['status'] ?? ''), ['approved', 'rejected'], true)) {
            throw new RuntimeException('Este item já foi decidido e não pode ser reatribuído.');
        }

        $this->queue->assignReviewer($queueId, $reviewerId);
    }

    public function approve(int $queueId, ?string $notes = null): void
    {
        $entry = $this->queue->findById($queueId);
        if ($entry === null) {
            throw new RuntimeException('Item de fila de revisão humana não encontrado.');
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $this->queue->updateDecision($queueId, 'approved', $notes);
            $this->documents->updateLatestStatusByOrderId((int) $entry['order_id'], 'approved');
            $this->orders->updateStatus((int) $entry['order_id'], 'ready');
            $this->revisions->markApproved((int) $entry['order_id'], $notes);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function reject(int $queueId, ?string $notes = null): void
    {
        $entry = $this->queue->findById($queueId);
        if ($entry === null) {
            throw new RuntimeException('Item de fila de revisão humana não encontrado.');
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $this->queue->updateDecision($queueId, 'rejected', $notes);
            $this->documents->updateLatestStatusByOrderId((int) $entry['order_id'], 'returned_for_revision');
            $this->orders->updateStatus((int) $entry['order_id'], 'revision_requested');
            $this->revisions->markReturnedForRevision((int) $entry['order_id'], $notes);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
