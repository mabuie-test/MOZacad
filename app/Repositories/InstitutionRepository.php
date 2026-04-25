<?php

declare(strict_types=1);

namespace App\Repositories;

final class InstitutionRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM institutions WHERE is_active=1 ORDER BY name')->fetchAll();
    }

    public function allForAdmin(int $limit = 300): array
    {
        $stmt = $this->db->prepare('SELECT * FROM institutions ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findRuleByInstitutionId(int $institutionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM institution_rules WHERE institution_id = :institution_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['institution_id' => $institutionId]);

        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM institutions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO institutions (name, short_name, slug, is_active, created_at, updated_at) VALUES (:name, :short_name, :slug, :is_active, NOW(), NOW())');
        $stmt->execute([
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE institutions SET name=:name, short_name=:short_name, slug=:slug, is_active=:is_active, updated_at=NOW() WHERE id=:id');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
    }
}
