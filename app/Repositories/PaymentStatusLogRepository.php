<?php
declare(strict_types=1);
namespace App\Repositories;
final class PaymentStatusLogRepository extends BaseRepository
{
    public function create(int $paymentId, string $status, string $providerStatus, array $payload, string $source): void
    {
        $s=$this->db->prepare('INSERT INTO payment_status_logs (payment_id,status,provider_status,payload_json,source,created_at) VALUES (:payment_id,:status,:provider_status,:payload_json,:source,NOW())');
        $s->execute(['payment_id'=>$paymentId,'status'=>$status,'provider_status'=>$providerStatus,'payload_json'=>json_encode($payload, JSON_UNESCAPED_UNICODE),'source'=>$source]);
    }
}
