<?php

declare(strict_types=1);

namespace App\Repositories;

final class DocumentComplianceValidationRepository extends BaseRepository
{
    public function create(int $documentId, int $version, array $result): void
    {
        $stmt = $this->db->prepare('INSERT INTO document_compliance_validations
            (generated_document_id, generated_document_version, is_compliant, critical_count, major_count, minor_count, non_conformities_json, created_at)
            VALUES (:document_id, :version, :is_compliant, :critical_count, :major_count, :minor_count, :non_conformities_json, NOW())');
        $stmt->execute([
            'document_id' => $documentId,
            'version' => $version,
            'is_compliant' => !empty($result['is_compliant']) ? 1 : 0,
            'critical_count' => (int) ($result['summary']['critical'] ?? 0),
            'major_count' => (int) ($result['summary']['major'] ?? 0),
            'minor_count' => (int) ($result['summary']['minor'] ?? 0),
            'non_conformities_json' => json_encode($result['non_conformities'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function findByDocument(int $documentId, int $version): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM document_compliance_validations WHERE generated_document_id = :document_id AND generated_document_version = :version ORDER BY id DESC LIMIT 1');
        $stmt->execute(['document_id' => $documentId, 'version' => $version]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findLatestByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT dcv.*
            FROM document_compliance_validations dcv
            INNER JOIN generated_documents gd ON gd.id = dcv.generated_document_id
            WHERE gd.order_id = :order_id
            ORDER BY dcv.id DESC
            LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
