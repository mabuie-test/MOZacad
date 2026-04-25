<?php

declare(strict_types=1);

namespace App\Repositories;

final class InstitutionRuleRepository extends BaseRepository
{
    public function all(int $limit = 300): array
    {
        $stmt = $this->db->prepare('SELECT r.*, i.name as institution_name
            FROM institution_rules r
            INNER JOIN institutions i ON i.id=r.institution_id
            ORDER BY r.updated_at DESC, r.id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function upsertByInstitution(int $institutionId, array $data): void
    {
        $existing = $this->db->prepare('SELECT id FROM institution_rules WHERE institution_id=:institution_id LIMIT 1');
        $existing->execute(['institution_id' => $institutionId]);
        $id = (int) ($existing->fetch()['id'] ?? 0);

        if ($id > 0) {
            $stmt = $this->db->prepare('UPDATE institution_rules SET references_style=:references_style, notes=:notes, front_page_rules_json=:front_page_rules_json, updated_at=NOW() WHERE id=:id');
            $stmt->execute([
                'id' => $id,
                'references_style' => $data['references_style'],
                'notes' => $data['notes'] ?? null,
                'front_page_rules_json' => $data['front_page_rules_json'],
            ]);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO institution_rules (institution_id, references_style, notes, front_page_rules_json, created_at, updated_at)
            VALUES (:institution_id, :references_style, :notes, :front_page_rules_json, NOW(), NOW())');
        $stmt->execute([
            'institution_id' => $institutionId,
            'references_style' => $data['references_style'],
            'notes' => $data['notes'] ?? null,
            'front_page_rules_json' => $data['front_page_rules_json'],
        ]);
    }
}
