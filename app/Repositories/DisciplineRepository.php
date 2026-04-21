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
        $s = $this->db->prepare('SELECT * FROM disciplines ORDER BY created_at DESC LIMIT :limit');
        $s->bindValue('limit', $limit, \PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll();
    }
}
