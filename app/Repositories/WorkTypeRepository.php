<?php

declare(strict_types=1);

namespace App\Repositories;

final class WorkTypeRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM work_types WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM work_types WHERE slug=:slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    public function getStructureByWorkType(int $workTypeId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM work_type_structures WHERE work_type_id = :work_type_id ORDER BY section_order ASC');
        $stmt->execute(['work_type_id' => $workTypeId]);

        return $stmt->fetchAll();
    }

    public function findInstitutionWorkTypeRule(int $institutionId, int $workTypeId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM institution_work_type_rules WHERE institution_id = :institution_id AND work_type_id = :work_type_id LIMIT 1');
        $stmt->execute(['institution_id' => $institutionId, 'work_type_id' => $workTypeId]);

        return $stmt->fetch() ?: null;
    }
}
