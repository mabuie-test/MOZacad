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
        $s = $this->db->prepare('SELECT * FROM courses ORDER BY created_at DESC LIMIT :limit');
        $s->bindValue('limit', $limit, \PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll();
    }
}
