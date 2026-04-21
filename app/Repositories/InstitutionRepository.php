<?php

declare(strict_types=1);

namespace App\Repositories;

final class InstitutionRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM institutions WHERE is_active=1 ORDER BY name')->fetchAll();
    }

    public function findRuleByInstitutionId(int $institutionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM institution_rules WHERE institution_id = :institution_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['institution_id' => $institutionId]);

        return $stmt->fetch() ?: null;
    }
}
