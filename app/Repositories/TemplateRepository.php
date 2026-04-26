<?php

declare(strict_types=1);

namespace App\Repositories;

final class TemplateRepository extends BaseRepository
{
    public function all(int $limit = 300): array
    {
        $stmt = $this->db->prepare('SELECT t.*, i.name as institution_name, w.name as work_type_name
            FROM templates t
            LEFT JOIN institutions i ON i.id=t.institution_id
            LEFT JOIN work_types w ON w.id=t.work_type_id
            ORDER BY t.updated_at DESC, t.id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function upsertPublishedTemplate(int $institutionId, int $workTypeId, string $filePath): void
    {
        $stmt = $this->db->prepare("SELECT id FROM templates WHERE institution_id=:institution_id AND work_type_id=:work_type_id AND template_type='work_type' ORDER BY id DESC LIMIT 1");
        $stmt->execute(['institution_id' => $institutionId, 'work_type_id' => $workTypeId]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $update = $this->db->prepare('UPDATE templates SET file_path=:file_path, is_active=1, updated_at=NOW() WHERE id=:id');
            $update->execute(['file_path' => $filePath, 'id' => $existingId]);
            return;
        }

        $insert = $this->db->prepare("INSERT INTO templates (institution_id,work_type_id,template_type,file_path,is_active,created_at,updated_at) VALUES (:institution_id,:work_type_id,'work_type',:file_path,1,NOW(),NOW())");
        $insert->execute(['institution_id' => $institutionId, 'work_type_id' => $workTypeId, 'file_path' => $filePath]);
    }
}
