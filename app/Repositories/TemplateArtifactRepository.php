<?php

declare(strict_types=1);

namespace App\Repositories;

final class TemplateArtifactRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM template_artifacts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function findActive(int $institutionId, ?int $workTypeId, string $artifactType): ?array
    {
        $sql = 'SELECT * FROM template_artifacts WHERE institution_id=:institution_id AND artifact_type=:artifact_type AND is_active=1';
        $params = ['institution_id' => $institutionId, 'artifact_type' => $artifactType];

        if ($workTypeId === null) {
            $sql .= ' AND work_type_id IS NULL';
        } else {
            $sql .= ' AND work_type_id=:work_type_id';
            $params['work_type_id'] = $workTypeId;
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function recordPublication(int $institutionId, ?int $workTypeId, string $artifactType, string $filePath, string $mimeType, int $fileSize, string $sha256, int $actorId): int
    {
        $deactivateSql = 'UPDATE template_artifacts SET is_active=0 WHERE institution_id=:institution_id AND artifact_type=:artifact_type';
        $params = ['institution_id' => $institutionId, 'artifact_type' => $artifactType];
        if ($workTypeId === null) {
            $deactivateSql .= ' AND work_type_id IS NULL';
        } else {
            $deactivateSql .= ' AND work_type_id=:work_type_id';
            $params['work_type_id'] = $workTypeId;
        }

        $this->db->prepare($deactivateSql)->execute($params);

        $s = $this->db->prepare('INSERT INTO template_artifacts (institution_id,work_type_id,artifact_type,file_path,mime_type,file_size,checksum_sha256,is_active,published_by_user_id,created_at) VALUES (:institution_id,:work_type_id,:artifact_type,:file_path,:mime_type,:file_size,:checksum_sha256,1,:published_by_user_id,NOW())');
        $s->execute([
            'institution_id' => $institutionId,
            'work_type_id' => $workTypeId,
            'artifact_type' => $artifactType,
            'file_path' => $filePath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'checksum_sha256' => $sha256,
            'published_by_user_id' => $actorId > 0 ? $actorId : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function activateArtifact(int $artifactId): bool
    {
        $artifact = $this->findById($artifactId);
        if ($artifact === null) {
            return false;
        }

        $deactivateSql = 'UPDATE template_artifacts
            SET is_active = 0
            WHERE institution_id = :institution_id
              AND artifact_type = :artifact_type';
        $params = [
            'institution_id' => (int) $artifact['institution_id'],
            'artifact_type' => (string) $artifact['artifact_type'],
        ];
        if ($artifact['work_type_id'] === null) {
            $deactivateSql .= ' AND work_type_id IS NULL';
        } else {
            $deactivateSql .= ' AND work_type_id = :work_type_id';
            $params['work_type_id'] = (int) $artifact['work_type_id'];
        }
        $this->db->prepare($deactivateSql)->execute($params);

        $activate = $this->db->prepare('UPDATE template_artifacts SET is_active = 1 WHERE id = :id');
        $activate->execute(['id' => $artifactId]);

        return $activate->rowCount() > 0;
    }

    public function listRecent(int $limit = 300): array
    {
        $sql = 'SELECT ta.*, i.name AS institution_name, wt.name AS work_type_name, u.name AS actor_name
            FROM template_artifacts ta
            INNER JOIN institutions i ON i.id = ta.institution_id
            LEFT JOIN work_types wt ON wt.id = ta.work_type_id
            LEFT JOIN users u ON u.id = ta.published_by_user_id
            ORDER BY ta.id DESC
            LIMIT :limit';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
