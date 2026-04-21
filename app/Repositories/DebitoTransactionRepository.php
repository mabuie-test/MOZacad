<?php
declare(strict_types=1);
namespace App\Repositories;
final class DebitoTransactionRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $sql='INSERT INTO debito_transactions (payment_id,wallet_id,debito_reference,request_payload_json,response_payload_json,provider_response_code,provider_response_message,status,created_at,updated_at)
        VALUES (:payment_id,:wallet_id,:debito_reference,:request_payload_json,:response_payload_json,:provider_response_code,:provider_response_message,:status,NOW(),NOW())';
        $this->db->prepare($sql)->execute($data);
        return (int)$this->db->lastInsertId();
    }
    public function updateStatusByReference(string $reference, string $status, array $statusPayload): void
    {
        $s=$this->db->prepare('UPDATE debito_transactions SET status=:status,last_status_payload_json=:payload,last_checked_at=NOW(),updated_at=NOW() WHERE debito_reference=:ref');
        $s->execute(['status'=>$status,'payload'=>json_encode($statusPayload, JSON_UNESCAPED_UNICODE),'ref'=>$reference]);
    }
}
