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
        private readonly OrderRepository $orders = new OrderRepository()
    ) {}

    public function request(int $orderId, int $userId, string $reason, ?int $generatedDocumentId = null, ?int $generatedDocumentVersion = null): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('O motivo de revisão é obrigatório.');
        }

        $this->orders->updateStatus($orderId, 'revision_requested');

        return $this->revisions->create($orderId, $userId, $reason, 'requested', $generatedDocumentId, $generatedDocumentVersion);
    }

    public function markReturnedForRevision(int $orderId, int $generatedDocumentId, int $generatedDocumentVersion, ?string $reviewerComment = null): void
    {
        $latest = $this->revisions->findLatestByOrderId($orderId);
        if ($latest === null) {
            return;
        }

        $this->revisions->updateStatus((int) $latest['id'], 'returned', $reviewerComment, $generatedDocumentId, $generatedDocumentVersion);
    }

    public function markApproved(int $orderId, int $generatedDocumentId, int $generatedDocumentVersion, ?string $reviewerComment = null): void
    {
        $latest = $this->revisions->findLatestByOrderId($orderId);
        if ($latest === null) {
            return;
        }

        $this->revisions->updateStatus((int) $latest['id'], 'approved', $reviewerComment, $generatedDocumentId, $generatedDocumentVersion);
    }
}
