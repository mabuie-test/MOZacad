<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use App\Repositories\AuditLogRepository;
use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\UserRepository;
use RuntimeException;

final class DocumentDownloadService
{
    public function __construct(
        private readonly GeneratedDocumentRepository $documents = new GeneratedDocumentRepository(),
        private readonly AuditLogRepository $auditLogs = new AuditLogRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {}

    public function resolve(int $documentId, int $actorUserId): array
    {
        $doc = $this->documents->findDetailedById($documentId);
        if ($doc === null) {
            throw new RuntimeException('Documento não encontrado.');
        }

        if (!$this->canDownload($doc, $actorUserId)) {
            throw new RuntimeException('Sem permissão para descarregar este documento.');
        }

        $path = $this->safePath((string) $doc['file_path']);
        if (!is_file($path)) {
            throw new RuntimeException('Ficheiro físico não encontrado no storage.');
        }

        $this->auditLogs->log($actorUserId, 'document.download', 'generated_document', $documentId, [
            'order_id' => (int) $doc['order_id'],
            'file_path' => $path,
        ]);

        return [
            'path' => $path,
            'download_name' => sprintf('pedido-%d-v%d.docx', (int) $doc['order_id'], (int) ($doc['version'] ?? 1)),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    private function canDownload(array $doc, int $actorUserId): bool
    {
        $status = (string) ($doc['status'] ?? '');
        $orderStatus = (string) ($doc['order_status'] ?? '');
        $own = (int) $doc['user_id'] === $actorUserId;

        $allowedStatus = $status === 'approved' || ($status === 'generated' && $orderStatus === 'ready');
        if (!$allowedStatus) {
            return false;
        }

        return $own || $this->isAdmin($actorUserId);
    }

    private function isAdmin(int $userId): bool
    {
        $admins = $this->users->listByRole('admin', 500);
        foreach ($admins as $admin) {
            if ((int) $admin['id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    private function safePath(string $path): string
    {
        $generatedBase = realpath(dirname(__DIR__, 2) . '/' . trim((string) Env::get('STORAGE_GENERATED_PATH', 'storage/generated'), '/'));
        $resolved = realpath($path);

        if ($generatedBase === false || $resolved === false || !str_starts_with($resolved, $generatedBase)) {
            throw new RuntimeException('Path inválido para download.');
        }

        return $resolved;
    }
}
