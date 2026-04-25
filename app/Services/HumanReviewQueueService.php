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
        private readonly RevisionService $revisions = new RevisionService(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService()
    ) {}

    public function enqueue(int $orderId, int $documentId, int $documentVersion, ?int $reviewerId = null): int
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $order = $this->orders->lockByIdForUpdate($orderId);
            if (!is_array($order)) {
                throw new RuntimeException('Pedido não encontrado para fila de revisão.');
            }

            $existingOpen = $this->queue->findOpenByOrderIdForUpdate($orderId);
            if ($existingOpen !== null) {
                $db->commit();
                return (int) $existingOpen['id'];
            }

            $id = $this->queue->enqueue($orderId, $documentId, $documentVersion, $reviewerId);
            $this->logger->info('human_review.queue.enqueued', ['order_id' => $orderId, 'queue_id' => $id, 'document_id' => $documentId, 'version' => $documentVersion]);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
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
        $this->logger->info('human_review.queue.assigned', ['queue_id' => $queueId, 'reviewer_id' => $reviewerId]);
    }

    public function approve(int $queueId, ?string $notes = null): void
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $entry = $this->queue->lockByIdForUpdate($queueId);
            if ($entry === null) {
                throw new RuntimeException('Item de fila de revisão humana não encontrado.');
            }
            if (in_array((string) ($entry['status'] ?? ''), ['approved', 'rejected'], true)) {
                throw new RuntimeException('Este item já foi decidido e não pode ser aprovado novamente.');
            }

            $latestDocument = $this->documents->findLatestByOrderId((int) $entry['order_id']);
            if ($latestDocument === null) {
                throw new RuntimeException('Não é possível aprovar sem documento gerado.');
            }
            if ((int) ($entry['generated_document_id'] ?? 0) !== (int) ($latestDocument['id'] ?? 0)) {
                throw new RuntimeException('Revisão humana não corresponde à versão documental mais recente.');
            }
            if ((int) ($entry['generated_document_version'] ?? 0) !== (int) ($latestDocument['version'] ?? 0)) {
                throw new RuntimeException('Revisão humana não corresponde à versão documental mais recente.');
            }
            if ((string) ($latestDocument['status'] ?? '') !== 'pending_human_review') {
                throw new RuntimeException('Documento não está pendente de revisão humana.');
            }

            $this->queue->updateDecision($queueId, 'approved', $notes);
            $this->documents->updateLatestStatusByOrderId((int) $entry['order_id'], 'approved');
            $this->orders->updateStatus((int) $entry['order_id'], 'ready');
            $this->revisions->markApproved((int) $entry['order_id'], (int) ($latestDocument['id'] ?? 0), (int) ($latestDocument['version'] ?? 0), $notes);
            $this->logger->info('human_review.cycle.approved', ['order_id' => (int) $entry['order_id'], 'queue_id' => $queueId, 'generated_document_id' => (int) ($latestDocument['id'] ?? 0), 'generated_document_version' => (int) ($latestDocument['version'] ?? 0)]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function reject(int $queueId, ?string $notes = null): void
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $entry = $this->queue->lockByIdForUpdate($queueId);
            if ($entry === null) {
                throw new RuntimeException('Item de fila de revisão humana não encontrado.');
            }
            if (in_array((string) ($entry['status'] ?? ''), ['approved', 'rejected'], true)) {
                throw new RuntimeException('Este item já foi decidido e não pode ser rejeitado novamente.');
            }

            $latestDocument = $this->documents->findLatestByOrderId((int) $entry['order_id']);
            if ($latestDocument === null) {
                throw new RuntimeException('Não é possível rejeitar sem documento gerado.');
            }
            if ((int) ($entry['generated_document_id'] ?? 0) !== (int) ($latestDocument['id'] ?? 0)) {
                throw new RuntimeException('Revisão humana não corresponde à versão documental mais recente.');
            }
            if ((int) ($entry['generated_document_version'] ?? 0) !== (int) ($latestDocument['version'] ?? 0)) {
                throw new RuntimeException('Revisão humana não corresponde à versão documental mais recente.');
            }
            if ((string) ($latestDocument['status'] ?? '') !== 'pending_human_review') {
                throw new RuntimeException('Documento não está pendente de revisão humana.');
            }

            $this->queue->updateDecision($queueId, 'rejected', $notes);
            $this->documents->updateLatestStatusByOrderId((int) $entry['order_id'], 'returned_for_revision');
            $this->orders->updateStatus((int) $entry['order_id'], 'returned_for_revision');
            $this->revisions->markReturnedForRevision((int) $entry['order_id'], (int) ($latestDocument['id'] ?? 0), (int) ($latestDocument['version'] ?? 0), $notes);
            $this->logger->info('human_review.cycle.rejected', ['order_id' => (int) $entry['order_id'], 'queue_id' => $queueId, 'generated_document_id' => (int) ($latestDocument['id'] ?? 0), 'generated_document_version' => (int) ($latestDocument['version'] ?? 0)]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
