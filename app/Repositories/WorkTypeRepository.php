<?php
declare(strict_types=1);
namespace App\Repositories;
final class WorkTypeRepository extends BaseRepository
{
    public function findById(int $id): ?array { $s=$this->db->prepare('SELECT * FROM work_types WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); return $s->fetch()?:null; }
    public function findBySlug(string $slug): ?array { $s=$this->db->prepare('SELECT * FROM work_types WHERE slug=:slug LIMIT 1'); $s->execute(['slug'=>$slug]); return $s->fetch()?:null; }
}
