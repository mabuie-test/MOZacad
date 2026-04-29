<?php

declare(strict_types=1);

namespace App\Repositories;

final class DeliveryChecklistRepository extends BaseRepository
{
    public const REQUIRED_ITEMS = ['normas', 'referencias', 'referencias_completas', 'antiplagio', 'anexos', 'linguagem'];

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



    public function updateItemStatus(int $documentId, int $version, string $item, bool $isChecked, string $status, int $actorId, ?string $notes): void
    {
        $this->ensureDefaults($documentId, $version);
        if (!in_array($item, self::REQUIRED_ITEMS, true)) {
            throw new \RuntimeException('Item de checklist inválido.');
        }

        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new \RuntimeException('Status de checklist inválido.');
        }

        $stmt = $this->db->prepare('UPDATE delivery_readiness_checklists
            SET is_checked = :is_checked,
                status = :status,
                checked_by = :checked_by,
                checked_at = NOW(),
                notes = :notes,
                updated_at = NOW()
            WHERE generated_document_id = :document_id
              AND generated_document_version = :version
              AND checklist_item = :checklist_item');
        $stmt->execute([
            'is_checked' => $isChecked ? 1 : 0,
            'status' => $status,
            'checked_by' => $actorId,
            'notes' => $notes,
            'document_id' => $documentId,
            'version' => $version,
            'checklist_item' => $item,
        ]);
    }

    public function signReviewer(int $documentId, int $version, int $actorId): void
    {
        $this->ensureDefaults($documentId, $version);
        $stmt = $this->db->prepare('UPDATE delivery_readiness_checklists
            SET reviewer_signed_by = :actor_id,
                reviewer_signed_at = NOW(),
                updated_at = NOW()
            WHERE generated_document_id = :document_id
              AND generated_document_version = :version');
        $stmt->execute([
            'actor_id' => $actorId,
            'document_id' => $documentId,
            'version' => $version,
        ]);
    }

    public function signApprover(int $documentId, int $version, int $actorId): void
    {
        $this->ensureDefaults($documentId, $version);
        $stmt = $this->db->prepare('UPDATE delivery_readiness_checklists
            SET approver_signed_by = :actor_id,
                approver_signed_at = NOW(),
                updated_at = NOW()
            WHERE generated_document_id = :document_id
              AND generated_document_version = :version');
        $stmt->execute([
            'actor_id' => $actorId,
            'document_id' => $documentId,
            'version' => $version,
        ]);
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
