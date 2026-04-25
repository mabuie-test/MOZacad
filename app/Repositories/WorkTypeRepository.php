<?php

declare(strict_types=1);

namespace App\Repositories;

final class WorkTypeRepository extends BaseRepository
{
    public function all(int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM work_types ORDER BY display_order ASC, id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

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

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO work_types (name, slug, description, is_active, base_price, default_complexity, requires_human_review, is_premium_type, display_order)
            VALUES (:name, :slug, :description, :is_active, :base_price, :default_complexity, :requires_human_review, :is_premium_type, :display_order)');
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'base_price' => $data['base_price'] ?? 0,
            'default_complexity' => $data['default_complexity'] ?? 'medium',
            'requires_human_review' => !empty($data['requires_human_review']) ? 1 : 0,
            'is_premium_type' => !empty($data['is_premium_type']) ? 1 : 0,
            'display_order' => $data['display_order'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE work_types SET name=:name, slug=:slug, description=:description, is_active=:is_active, base_price=:base_price,
            default_complexity=:default_complexity, requires_human_review=:requires_human_review, is_premium_type=:is_premium_type, display_order=:display_order WHERE id=:id');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'base_price' => $data['base_price'] ?? 0,
            'default_complexity' => $data['default_complexity'] ?? 'medium',
            'requires_human_review' => !empty($data['requires_human_review']) ? 1 : 0,
            'is_premium_type' => !empty($data['is_premium_type']) ? 1 : 0,
            'display_order' => $data['display_order'] ?? 0,
        ]);
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
