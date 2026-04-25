<?php
declare(strict_types=1);
namespace App\Repositories;
final class DisciplineRepository extends BaseRepository
{
    public function byCourse(int $courseId): array
    {
        $s=$this->db->prepare('SELECT * FROM disciplines WHERE is_active=1 AND course_id=:course_id ORDER BY name');
        $s->execute(['course_id'=>$courseId]);
        return $s->fetchAll();
    }

    public function all(int $limit = 200): array
    {
        $s = $this->db->prepare('SELECT d.*, c.name as course_name, i.name as institution_name
            FROM disciplines d
            LEFT JOIN courses c ON c.id=d.course_id
            LEFT JOIN institutions i ON i.id=d.institution_id
            ORDER BY d.created_at DESC LIMIT :limit');
        $s->bindValue('limit', $limit, \PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll();
    }

    public function create(array $data): int
    {
        $s = $this->db->prepare('INSERT INTO disciplines (institution_id, course_id, name, code, is_active, created_at, updated_at) VALUES (:institution_id, :course_id, :name, :code, :is_active, NOW(), NOW())');
        $s->execute([
            'institution_id' => $data['institution_id'] ?: null,
            'course_id' => $data['course_id'] ?: null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $s = $this->db->prepare('UPDATE disciplines SET institution_id=:institution_id, course_id=:course_id, name=:name, code=:code, is_active=:is_active, updated_at=NOW() WHERE id=:id');
        $s->execute([
            'id' => $id,
            'institution_id' => $data['institution_id'] ?: null,
            'course_id' => $data['course_id'] ?: null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
    }
}
