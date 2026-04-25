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
}
