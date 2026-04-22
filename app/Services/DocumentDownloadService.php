<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\GeneratedDocumentRepository;
use RuntimeException;

final class DocumentDownloadService
{
    public function __construct(
        private readonly GeneratedDocumentRepository $documents = new GeneratedDocumentRepository(),
        private readonly AuditLogRepository $auditLogs = new AuditLogRepository(),
        private readonly AuthorizationService $authorization = new AuthorizationService(),
        private readonly StoragePathService $paths = new StoragePathService(),
    ) {}

    public function resolve(int $documentId, int $actorUserId): array
    {
        $doc = $this->documents->findDetailedById($documentId);
        if ($doc === null) throw new RuntimeException('Documento não encontrado.');

        $status = (string) ($doc['status'] ?? '');
        $orderStatus = (string) ($doc['order_status'] ?? '');
        $isLatest = $this->documents->isLatestVersion((int) $doc['id'], (int) $doc['order_id']);
        $allowedStatus = $isLatest && ($status === 'approved' || ($status === 'generated' && $orderStatus === 'ready'));
        $own = (int) $doc['user_id'] === $actorUserId;
        if (!$allowedStatus || (!$own && !$this->authorization->isAdmin($actorUserId))) {
            throw new RuntimeException('Sem permissão para descarregar este documento.');
        }

        $path = $this->paths->ensurePathInside((string) $doc['file_path'], $this->paths->generatedBase());
        if (!is_file($path)) throw new RuntimeException('Ficheiro físico não encontrado no storage.');

        $this->auditLogs->log($actorUserId, 'document.download', 'generated_document', $documentId, ['order_id' => (int) $doc['order_id'], 'file_path' => $path]);

        return [
            'path' => $path,
            'download_name' => sprintf('pedido-%d-v%d.docx', (int) $doc['order_id'], (int) ($doc['version'] ?? 1)),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
    }
}
