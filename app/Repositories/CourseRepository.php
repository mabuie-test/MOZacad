<?php
declare(strict_types=1);
namespace App\Repositories;
final class CourseRepository extends BaseRepository { public function byInstitution(int $institutionId): array { $s=$this->db->prepare('SELECT * FROM courses WHERE is_active=1 AND institution_id=:institution_id ORDER BY name'); $s->execute(['institution_id'=>$institutionId]); return $s->fetchAll(); } }
