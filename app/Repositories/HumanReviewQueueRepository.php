<?php
declare(strict_types=1);
namespace App\Repositories;
final class HumanReviewQueueRepository extends BaseRepository
{
    public function enqueue(int $orderId): int { $s=$this->db->prepare("INSERT INTO human_review_queue (order_id,status,created_at,updated_at) VALUES (:order_id,'pending',NOW(),NOW())"); $s->execute(['order_id'=>$orderId]); return (int)$this->db->lastInsertId(); }
    public function listQueue(int $limit = 100): array { $s=$this->db->prepare('SELECT * FROM human_review_queue ORDER BY created_at DESC LIMIT :limit'); $s->bindValue('limit',$limit,\PDO::PARAM_INT); $s->execute(); return $s->fetchAll(); }
}
