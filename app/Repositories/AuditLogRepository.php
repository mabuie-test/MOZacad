<?php
declare(strict_types=1);
namespace App\Repositories;
final class AuditLogRepository extends BaseRepository
{
    public function log(?int $actorId, string $action, string $subjectType, ?int $subjectId = null, array $payload = [], ?string $permissionCode = null): void
    {
        $s=$this->db->prepare('INSERT INTO audit_logs (actor_id,action,subject_type,subject_id,payload_json,permission_code,created_at) VALUES (:actor_id,:action,:subject_type,:subject_id,:payload_json,:permission_code,NOW())');
        $s->execute([
            'actor_id'=>$actorId,
            'action'=>$action,
            'subject_type'=>$subjectType,
            'subject_id'=>$subjectId,
            'payload_json'=>json_encode($payload, JSON_UNESCAPED_UNICODE),
            'permission_code'=>$permissionCode,
        ]);
    }

    public function listBySubject(string $subjectType, int $subjectId, int $limit = 100): array
    {
        $s = $this->db->prepare('SELECT * FROM audit_logs
            WHERE subject_type = :subject_type AND subject_id = :subject_id
            ORDER BY created_at DESC
            LIMIT :limit');
        $s->bindValue('subject_type', $subjectType);
        $s->bindValue('subject_id', $subjectId, \PDO::PARAM_INT);
        $s->bindValue('limit', $limit, \PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll();
    }
}
