<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\DeliveryChecklistRepository;
use RuntimeException;

final class DocumentDownloadService
{
    public function __construct(
        private readonly GeneratedDocumentRepository $documents = new GeneratedDocumentRepository(),
        private readonly AuditLogRepository $auditLogs = new AuditLogRepository(),
        private readonly AuthorizationService $authorization = new AuthorizationService(),
        private readonly StoragePathService $paths = new StoragePathService(),
        private readonly DeliveryChecklistRepository $deliveryChecklist = new DeliveryChecklistRepository(),
    ) {}

    public function resolve(int $documentId, int $actorUserId): array
    {
        $doc = $this->documents->findDetailedById($documentId);
        if ($doc === null) throw new RuntimeException('Documento não encontrado.');

        $status = (string) ($doc['status'] ?? '');
        $orderStatus = (string) ($doc['order_status'] ?? '');
        $isLatest = $this->documents->isLatestVersion((int) $doc['id'], (int) $doc['order_id']);
        // Política de transição: `approved` é aceito apenas para retrocompatibilidade
        // durante a migração para o fluxo final com `final_approved`.
        // Janela de descontinuação alvo: remover `approved` após 2026-09-30.
        $allowedDocumentStatuses = ['final_approved', 'approved'];
        $allowedStatus = $isLatest
            && $orderStatus === 'ready'
            && in_array($status, $allowedDocumentStatuses, true);
        $own = (int) $doc['user_id'] === $actorUserId;

        if (!$allowedStatus) {
            $this->auditLogs->log($actorUserId, 'document.download.blocked', 'generated_document', $documentId, [
                'reason' => 'state_not_eligible',
                'order_id' => (int) $doc['order_id'],
                'order_status' => $orderStatus,
                'document_status' => $status,
                'is_latest_version' => $isLatest,
                'allowed_order_statuses' => ['ready'],
                'allowed_document_statuses' => $allowedDocumentStatuses,
            ]);
            throw new RuntimeException('Sem permissão para descarregar este documento.');
        }

        if (!$own && !$this->authorization->isAdmin($actorUserId)) {
            $this->auditLogs->log($actorUserId, 'document.download.blocked', 'generated_document', $documentId, [
                'reason' => 'not_owner_or_admin',
                'order_id' => (int) $doc['order_id'],
            ]);
            throw new RuntimeException('Sem permissão para descarregar este documento.');
        }

        if (!$this->deliveryChecklist->isCompleteAndApproved((int) $doc['id'], (int) ($doc['version'] ?? 1))) {
            $this->auditLogs->log($actorUserId, 'document.download.blocked', 'generated_document', $documentId, [
                'reason' => 'delivery_checklist_not_approved',
                'order_id' => (int) $doc['order_id'],
                'version' => (int) ($doc['version'] ?? 1),
            ]);
            throw new RuntimeException('Download final bloqueado: checklist de prontidão de entrega está incompleto ou sem assinaturas internas.');
        }


        if ($status === 'approved') {
            $this->auditLogs->log($actorUserId, 'document.download.legacy_status_served', 'generated_document', $documentId, [
                'reason' => 'legacy_status_approved',
                'migration_target_status' => 'final_approved',
                'deprecation_target_date' => '2026-09-30',
                'order_id' => (int) $doc['order_id'],
                'version' => (int) ($doc['version'] ?? 1),
            ]);
        }

        $path = $this->paths->ensurePathInside((string) $doc['file_path'], $this->paths->generatedBase());
        if (!is_file($path) || filesize($path) <= 0) {
            throw new RuntimeException('Ficheiro físico não encontrado no storage.');
        }

        $this->auditLogs->log($actorUserId, 'document.download', 'generated_document', $documentId, [
            'delivery_checklist_gate' => 'passed',
            'order_id' => (int) $doc['order_id'],
            'file_name' => basename($path),
            'version' => (int) ($doc['version'] ?? 1),
        ]);

        return [
            'path' => $path,
            'download_name' => sprintf('pedido-%d-v%d.docx', (int) $doc['order_id'], (int) ($doc['version'] ?? 1)),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
    }
}
