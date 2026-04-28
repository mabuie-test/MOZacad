<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Env;
use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\HumanReviewDecisionRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\OrderRepository;
use App\Repositories\WorkTypeRepository;
use RuntimeException;

final class HumanReviewQueueService
{
    public function __construct(
        private readonly HumanReviewQueueRepository $queue = new HumanReviewQueueRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly GeneratedDocumentRepository $documents = new GeneratedDocumentRepository(),
        private readonly RevisionService $revisions = new RevisionService(),
        private readonly WorkTypeRepository $workTypes = new WorkTypeRepository(),
        private readonly HumanReviewDecisionRepository $decisionHistory = new HumanReviewDecisionRepository(),
        private readonly AuthorizationService $authorization = new AuthorizationService(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService()
    ) {}

    public function enqueue(int $orderId, int $documentId, int $documentVersion, ?int $reviewerId = null, ?int $createdBy = null): int
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

            $requiredApprovals = $this->requiredApprovalsForOrder($order);
            $id = $this->queue->enqueue($orderId, $documentId, $documentVersion, $reviewerId, $createdBy);
            $this->queue->setRequiredApprovals($id, $requiredApprovals);
            $this->logger->info('human_review.queue.enqueued', ['order_id' => $orderId, 'queue_id' => $id, 'document_id' => $documentId, 'version' => $documentVersion, 'required_approvals' => $requiredApprovals]);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function assignReviewer(int $queueId, int $reviewerId, int $actorId): void
    {
        $entry = $this->queue->findById($queueId);
        if ($entry === null) {
            throw new RuntimeException('Item de fila de revisão humana não encontrado.');
        }

        $this->assertReviewerPermissions($actorId, false);
        $this->assertReviewerPermissions($reviewerId, false);

        if (in_array((string) ($entry['status'] ?? ''), ['final_approved', 'rejected'], true)) {
            throw new RuntimeException('Este item já foi decidido e não pode ser reatribuído.');
        }

        $this->queue->assignReviewer($queueId, $reviewerId, $actorId);
        $this->logger->info('human_review.queue.assigned', ['queue_id' => $queueId, 'reviewer_id' => $reviewerId, 'assigned_by' => $actorId]);
    }

    public function approve(int $queueId, int $actorId, ?string $notes = null, bool $enforceAssignedReviewer = true): void
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $entry = $this->queue->lockByIdForUpdate($queueId);
            if ($entry === null) {
                throw new RuntimeException('Item de fila de revisão humana não encontrado.');
            }
            if (in_array((string) ($entry['status'] ?? ''), ['final_approved', 'rejected'], true)) {
                throw new RuntimeException('Este item já foi decidido e não pode ser aprovado novamente.');
            }

            $this->assertReviewerPermissions($actorId, $enforceAssignedReviewer, $entry);
            $this->assertSegregationOfDuties($entry, $actorId);

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
            if (!in_array((string) ($latestDocument['status'] ?? ''), ['pending_human_review', 'qa_approved'], true)) {
                throw new RuntimeException('Documento não está pendente de revisão humana.');
            }

            $requiredApprovals = max(1, (int) ($entry['required_approvals'] ?? $this->requiredApprovalsByOrderId((int) $entry['order_id'])));
            $approvalCount = (int) ($entry['approval_count'] ?? 0);
            if ($approvalCount + 1 < $requiredApprovals) {
                $this->queue->markQaApproved($queueId, $actorId, $notes);
                $this->documents->updateLatestStatusByOrderId((int) $entry['order_id'], 'qa_approved');
                $this->orders->updateStatus((int) $entry['order_id'], 'qa_approved');
                $this->decisionHistory->create($queueId, $actorId, 'qa_approved', 'approve', $notes);
                $this->logger->info('human_review.cycle.qa_approved', ['order_id' => (int) $entry['order_id'], 'queue_id' => $queueId, 'actor_id' => $actorId, 'approval_count' => $approvalCount + 1, 'required_approvals' => $requiredApprovals]);
                $db->commit();
                return;
            }

            $this->queue->markFinalApproved($queueId, $actorId, $notes);
            $this->documents->updateLatestStatusByOrderId((int) $entry['order_id'], 'final_approved');
            $this->orders->updateStatus((int) $entry['order_id'], 'ready');
            $this->revisions->markApproved((int) $entry['order_id'], (int) ($latestDocument['id'] ?? 0), (int) ($latestDocument['version'] ?? 0), $notes);
            $this->decisionHistory->create($queueId, $actorId, 'final_approved', 'approve', $notes);
            $this->logger->info('human_review.cycle.final_approved', ['order_id' => (int) $entry['order_id'], 'queue_id' => $queueId, 'actor_id' => $actorId, 'generated_document_id' => (int) ($latestDocument['id'] ?? 0), 'generated_document_version' => (int) ($latestDocument['version'] ?? 0)]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function reject(int $queueId, int $actorId, ?string $notes = null, bool $enforceAssignedReviewer = true): void
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $entry = $this->queue->lockByIdForUpdate($queueId);
            if ($entry === null) {
                throw new RuntimeException('Item de fila de revisão humana não encontrado.');
            }
            if (in_array((string) ($entry['status'] ?? ''), ['final_approved', 'rejected'], true)) {
                throw new RuntimeException('Este item já foi decidido e não pode ser rejeitado novamente.');
            }

            $this->assertReviewerPermissions($actorId, $enforceAssignedReviewer, $entry);
            $this->assertSegregationOfDuties($entry, $actorId);

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
            if (!in_array((string) ($latestDocument['status'] ?? ''), ['pending_human_review', 'qa_approved'], true)) {
                throw new RuntimeException('Documento não está pendente de revisão humana.');
            }

            $this->queue->markRejected($queueId, $actorId, $notes);
            $this->documents->updateLatestStatusByOrderId((int) $entry['order_id'], 'returned_for_revision');
            $this->orders->updateStatus((int) $entry['order_id'], 'returned_for_revision');
            $this->revisions->markReturnedForRevision((int) $entry['order_id'], (int) ($latestDocument['id'] ?? 0), (int) ($latestDocument['version'] ?? 0), $notes);
            $this->decisionHistory->create($queueId, $actorId, 'rejected', 'reject', $notes);
            $this->logger->info('human_review.cycle.rejected', ['order_id' => (int) $entry['order_id'], 'queue_id' => $queueId, 'actor_id' => $actorId, 'generated_document_id' => (int) ($latestDocument['id'] ?? 0), 'generated_document_version' => (int) ($latestDocument['version'] ?? 0)]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function assertReviewerPermissions(int $actorId, bool $enforceAssignedReviewer, ?array $entry = null): void
    {
        if (!$this->authorization->can($actorId, 'human_review.approve')) {
            throw new RuntimeException('Utilizador sem permissão para decidir revisão humana.');
        }

        if ($enforceAssignedReviewer && is_array($entry)) {
            $assignedReviewerId = (int) ($entry['reviewer_id'] ?? 0);
            if ($assignedReviewerId > 0 && $assignedReviewerId !== $actorId) {
                throw new RuntimeException('Apenas o revisor atribuído pode decidir esta etapa.');
            }
        }
    }

    private function assertSegregationOfDuties(array $entry, int $actorId): void
    {
        if ($actorId <= 0) {
            throw new RuntimeException('Ator inválido para decisão de revisão humana.');
        }

        $createdBy = (int) ($entry['created_by'] ?? 0);
        $assignedBy = (int) ($entry['assigned_by'] ?? 0);
        if (($createdBy > 0 && $createdBy === $actorId) || ($assignedBy > 0 && $assignedBy === $actorId)) {
            throw new RuntimeException('Segregation of duties: o mesmo ator não pode criar/atribuir e aprovar no mesmo ciclo.');
        }

        $lastDecidedBy = (int) ($entry['last_decided_by'] ?? 0);
        if ((string) ($entry['status'] ?? '') === 'qa_approved' && $lastDecidedBy > 0 && $lastDecidedBy === $actorId) {
            throw new RuntimeException('A aprovação final deve ser feita por um segundo revisor.');
        }
    }

    private function requiredApprovalsByOrderId(int $orderId): int
    {
        $order = $this->orders->findById($orderId);
        if (!is_array($order)) {
            return 1;
        }

        return $this->requiredApprovalsForOrder($order);
    }

    private function requiredApprovalsForOrder(array $order): int
    {
        $workTypeId = (int) ($order['work_type_id'] ?? 0);
        if ($workTypeId <= 0) {
            return 1;
        }

        $workType = $this->workTypes->findById($workTypeId);
        if (!is_array($workType)) {
            return 1;
        }

        $criticalTypes = array_filter(array_map('trim', explode(',', (string) Env::get('HUMAN_REVIEW_CRITICAL_WORK_TYPES', 'monografia,tese'))));
        $criticalSecondApproval = filter_var((string) Env::get('HUMAN_REVIEW_REQUIRE_SECOND_APPROVAL_FOR_CRITICAL', true), FILTER_VALIDATE_BOOL);

        if (!$criticalSecondApproval) {
            return 1;
        }

        return in_array((string) ($workType['slug'] ?? ''), $criticalTypes, true) ? 2 : 1;
    }
}
