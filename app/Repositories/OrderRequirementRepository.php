<?php
declare(strict_types=1);
namespace App\Repositories;
final class OrderRequirementRepository extends BaseRepository
{
    public function findByOrderId(int $orderId): ?array { $s=$this->db->prepare('SELECT * FROM order_requirements WHERE order_id=:order_id LIMIT 1'); $s->execute(['order_id'=>$orderId]); return $s->fetch()?:null; }
}
