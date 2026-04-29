<?php
declare(strict_types=1);
namespace App\Repositories;

final class AuditLogRepository extends BaseRepository
{
    public function log(?int $actorId, string $action, string $subjectType, ?int $subjectId = null, array $payload = [], ?string $permissionCode = null): void
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $previousHash = $this->latestEventHash();
        $eventHash = hash('sha256', implode('|', [
            (string) $actorId,
            $action,
            $subjectType,
            (string) $subjectId,
            (string) $payloadJson,
            (string) $permissionCode,
            (string) $previousHash,
        ]));

        $s=$this->db->prepare('INSERT INTO audit_logs (actor_id,action,subject_type,subject_id,payload_json,permission_code,previous_hash,event_hash,created_at) VALUES (:actor_id,:action,:subject_type,:subject_id,:payload_json,:permission_code,:previous_hash,:event_hash,NOW())');
        $s->execute([
            'actor_id'=>$actorId,
            'action'=>$action,
            'subject_type'=>$subjectType,
            'subject_id'=>$subjectId,
            'payload_json'=>$payloadJson,
            'permission_code'=>$permissionCode,
            'previous_hash'=>$previousHash,
            'event_hash'=>$eventHash,
        ]);
    }

    public function search(array $filters, int $limit = 250): array
    {
        $where = [];
        $params = [];

        if ((int)($filters['actor_id'] ?? 0) > 0) {
            $where[] = 'actor_id = :actor_id';
            $params['actor_id'] = (int)$filters['actor_id'];
        }
        if (trim((string)($filters['action'] ?? '')) !== '') {
            $where[] = 'action = :action';
            $params['action'] = trim((string)$filters['action']);
        }
        if (trim((string)($filters['subject_type'] ?? '')) !== '') {
            $where[] = 'subject_type = :subject_type';
            $params['subject_type'] = trim((string)$filters['subject_type']);
        }
        if ((int)($filters['subject_id'] ?? 0) > 0) {
            $where[] = 'subject_id = :subject_id';
            $params['subject_id'] = (int)$filters['subject_id'];
        }
        if ((int)($filters['order_id'] ?? 0) > 0) {
            $where[] = '(subject_type = "order" AND subject_id = :order_id)';
            $params['order_id'] = (int)$filters['order_id'];
        }
        if (trim((string)($filters['from'] ?? '')) !== '') {
            $where[] = 'created_at >= :from_date';
            $params['from_date'] = trim((string)$filters['from']) . ' 00:00:00';
        }
        if (trim((string)($filters['to'] ?? '')) !== '') {
            $where[] = 'created_at <= :to_date';
            $params['to_date'] = trim((string)$filters['to']) . ' 23:59:59';
        }

        $sql = 'SELECT * FROM audit_logs';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';

        $s = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $s->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $s->bindValue('limit', $limit, \PDO::PARAM_INT);
        $s->execute();

        return $s->fetchAll();
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

    private function latestEventHash(): ?string
    {
        $s = $this->db->query('SELECT event_hash FROM audit_logs ORDER BY id DESC LIMIT 1');
        $row = $s ? $s->fetch() : false;
        return is_array($row) ? (string)($row['event_hash'] ?? '') ?: null : null;
    }
}
