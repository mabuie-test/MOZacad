<?php

declare(strict_types=1);

namespace App\Repositories;

final class DeliveryChecklistRepository extends BaseRepository
{
    public const REQUIRED_ITEMS = ['normas', 'referencias', 'antiplagio', 'anexos', 'linguagem'];

    public function ensureDefaults(int $documentId, int $version): void
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO delivery_readiness_checklists
            (generated_document_id, generated_document_version, checklist_item, status, created_at, updated_at)
            VALUES (:document_id, :version, :checklist_item, :status, NOW(), NOW())');

        foreach (self::REQUIRED_ITEMS as $item) {
            $stmt->execute([
                'document_id' => $documentId,
                'version' => $version,
                'checklist_item' => $item,
                'status' => 'pending',
            ]);
        }
    }

    public function listByDocument(int $documentId, int $version): array
    {
        $stmt = $this->db->prepare('SELECT * FROM delivery_readiness_checklists WHERE generated_document_id = :document_id AND generated_document_version = :version ORDER BY checklist_item');
        $stmt->execute(['document_id' => $documentId, 'version' => $version]);
        return $stmt->fetchAll();
    }

    public function isCompleteAndApproved(int $documentId, int $version): bool
    {
        $this->ensureDefaults($documentId, $version);
        $stmt = $this->db->prepare("SELECT COUNT(*) AS pending_count
            FROM delivery_readiness_checklists
            WHERE generated_document_id = :document_id
              AND generated_document_version = :version
              AND (is_checked = 0 OR status <> 'approved' OR reviewer_signed_by IS NULL OR reviewer_signed_at IS NULL OR approver_signed_by IS NULL OR approver_signed_at IS NULL)");
        $stmt->execute(['document_id' => $documentId, 'version' => $version]);
        return (int) ($stmt->fetch()['pending_count'] ?? 1) === 0;
    }

    public function summarizeByQueue(int $limit = 300): array
    {
        $stmt = $this->db->prepare("SELECT generated_document_id, generated_document_version,
                COUNT(*) AS total_items,
                SUM(CASE WHEN is_checked = 1 THEN 1 ELSE 0 END) AS checked_items,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_items,
                SUM(CASE WHEN status <> 'approved' OR is_checked = 0 OR reviewer_signed_by IS NULL OR approver_signed_by IS NULL THEN 1 ELSE 0 END) AS blocking_items
            FROM delivery_readiness_checklists
            GROUP BY generated_document_id, generated_document_version
            ORDER BY generated_document_id DESC
            LIMIT :limit");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
