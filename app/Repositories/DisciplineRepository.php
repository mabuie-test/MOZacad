<?php
declare(strict_types=1);
namespace App\Repositories;
final class DisciplineRepository extends BaseRepository { public function byCourse(int $courseId): array { $s=$this->db->prepare('SELECT * FROM disciplines WHERE is_active=1 AND course_id=:course_id ORDER BY name'); $s->execute(['course_id'=>$courseId]); return $s->fetchAll(); } }
