<?php

declare(strict_types=1);

namespace App\Repositories;

final class InstitutionWorkTypeRuleRepository extends BaseRepository
{
    public function all(int $limit = 300): array
    {
        $stmt = $this->db->prepare('SELECT r.*, i.name as institution_name, w.name as work_type_name
            FROM institution_work_type_rules r
            INNER JOIN institutions i ON i.id=r.institution_id
            INNER JOIN work_types w ON w.id=r.work_type_id
            ORDER BY r.id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function upsert(int $institutionId, int $workTypeId, array $data): void
    {
        $existing = $this->db->prepare('SELECT id FROM institution_work_type_rules WHERE institution_id=:institution_id AND work_type_id=:work_type_id LIMIT 1');
        $existing->execute(['institution_id' => $institutionId, 'work_type_id' => $workTypeId]);
        $id = (int) ($existing->fetch()['id'] ?? 0);

        if ($id > 0) {
            $stmt = $this->db->prepare('UPDATE institution_work_type_rules SET custom_structure_json=:custom_structure_json, custom_visual_rules_json=:custom_visual_rules_json,
                custom_reference_rules_json=:custom_reference_rules_json, notes=:notes WHERE id=:id');
            $stmt->execute([
                'id' => $id,
                'custom_structure_json' => $data['custom_structure_json'],
                'custom_visual_rules_json' => $data['custom_visual_rules_json'],
                'custom_reference_rules_json' => $data['custom_reference_rules_json'],
                'notes' => $data['notes'] ?? null,
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO institution_work_type_rules (institution_id, work_type_id, custom_structure_json, custom_visual_rules_json, custom_reference_rules_json, notes)
            VALUES (:institution_id, :work_type_id, :custom_structure_json, :custom_visual_rules_json, :custom_reference_rules_json, :notes)');
        $stmt->execute([
            'institution_id' => $institutionId,
            'work_type_id' => $workTypeId,
            'custom_structure_json' => $data['custom_structure_json'],
            'custom_visual_rules_json' => $data['custom_visual_rules_json'],
            'custom_reference_rules_json' => $data['custom_reference_rules_json'],
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
