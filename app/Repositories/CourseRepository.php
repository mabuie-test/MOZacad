<?php
declare(strict_types=1);
namespace App\Repositories;
final class CourseRepository extends BaseRepository
{
    public function byInstitution(int $institutionId): array
    {
        $s=$this->db->prepare('SELECT * FROM courses WHERE is_active=1 AND institution_id=:institution_id ORDER BY name');
        $s->execute(['institution_id'=>$institutionId]);
        return $s->fetchAll();
    }

    public function all(int $limit = 200): array
    {
        $s = $this->db->prepare('SELECT c.*, i.name as institution_name FROM courses c LEFT JOIN institutions i ON i.id=c.institution_id ORDER BY c.created_at DESC LIMIT :limit');
        $s->bindValue('limit', $limit, \PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll();
    }

    public function create(array $data): int
    {
        $s = $this->db->prepare('INSERT INTO courses (institution_id, name, code, is_active, created_at, updated_at) VALUES (:institution_id, :name, :code, :is_active, NOW(), NOW())');
        $s->execute([
            'institution_id' => $data['institution_id'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $s = $this->db->prepare('UPDATE courses SET institution_id=:institution_id, name=:name, code=:code, is_active=:is_active, updated_at=NOW() WHERE id=:id');
        $s->execute([
            'id' => $id,
            'institution_id' => $data['institution_id'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
    }
}
