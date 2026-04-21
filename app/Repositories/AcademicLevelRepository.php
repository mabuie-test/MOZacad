<?php
declare(strict_types=1);
namespace App\Repositories;
final class AcademicLevelRepository extends BaseRepository
{
    public function findById(int $id): ?array { $s=$this->db->prepare('SELECT * FROM academic_levels WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); return $s->fetch()?:null; }
}
