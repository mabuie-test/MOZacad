<?php
declare(strict_types=1);
namespace App\Repositories;
final class UserRepository extends BaseRepository
{
    public function findById(int $id): ?array { $s=$this->db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); return $s->fetch()?:null; }
    public function all(int $limit = 100): array { $s=$this->db->prepare('SELECT * FROM users ORDER BY created_at DESC LIMIT :limit'); $s->bindValue('limit',$limit,\PDO::PARAM_INT); $s->execute(); return $s->fetchAll(); }
}
