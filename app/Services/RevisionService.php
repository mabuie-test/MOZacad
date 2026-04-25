<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\RevisionRepository;
use RuntimeException;

final class RevisionService
{
    public function __construct(
        private readonly RevisionRepository $revisions = new RevisionRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
    ) {}

    public function request(int $orderId, int $userId, string $reason, ?int $generatedDocumentId = null, ?int $generatedDocumentVersion = null): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('O motivo de revisão é obrigatório.');
        }

        $order = $this->orders->findById($orderId);
        if ($order === null || (int) ($order['user_id'] ?? 0) !== $userId) {
            throw new RuntimeException('Pedido não encontrado para revisão.');
        }

        $status = (string) ($order['status'] ?? '');
        if (in_array($status, ['pending_payment', 'failed', 'cancelled', 'expired'], true)) {
            throw new RuntimeException('Pedido ainda não está elegível para revisão.');
        }

        $this->orders->updateStatus($orderId, 'revision_requested');
        $revisionId = $this->revisions->create($orderId, $userId, $reason, 'requested', $generatedDocumentId, $generatedDocumentVersion);

        $this->logger->info('revision.requested', ['order_id' => $orderId, 'user_id' => $userId, 'revision_id' => $revisionId]);
        return $revisionId;
    }

    public function markReturnedForRevision(int $orderId, int $generatedDocumentId, int $generatedDocumentVersion, ?string $reviewerComment = null): void
    {
        $latest = $this->revisions->findLatestByOrderId($orderId);
        if ($latest === null) {
            return;
        }

        $this->revisions->updateStatus((int) $latest['id'], 'returned', $reviewerComment, $generatedDocumentId, $generatedDocumentVersion);
        $this->orders->updateStatus($orderId, 'returned_for_revision');
        $this->logger->info('revision.returned', ['order_id' => $orderId, 'revision_id' => (int) $latest['id']]);
    }

    public function markApproved(int $orderId, int $generatedDocumentId, int $generatedDocumentVersion, ?string $reviewerComment = null): void
    {
        $latest = $this->revisions->findLatestByOrderId($orderId);
        if ($latest === null) {
            return;
        }

        $this->revisions->updateStatus((int) $latest['id'], 'approved', $reviewerComment, $generatedDocumentId, $generatedDocumentVersion);
        $this->logger->info('revision.approved', ['order_id' => $orderId, 'revision_id' => (int) $latest['id']]);
    }
}
