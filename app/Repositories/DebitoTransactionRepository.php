<?php

declare(strict_types=1);

namespace App\Repositories;

final class DebitoTransactionRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $sql = 'INSERT INTO debito_transactions (
                payment_id,
                wallet_id,
                api_version,
                wallet_code,
                debito_reference,
                provider_payment_id,
                provider_reference,
                request_payload_json,
                response_payload_json,
                provider_response_code,
                provider_response_message,
                status,
                created_at,
                updated_at
            ) VALUES (
                :payment_id,
                :wallet_id,
                :api_version,
                :wallet_code,
                :debito_reference,
                :provider_payment_id,
                :provider_reference,
                :request_payload_json,
                :response_payload_json,
                :provider_response_code,
                :provider_response_message,
                :status,
                NOW(),
                NOW()
            )';

        $payload = [
            'payment_id' => $data['payment_id'] ?? null,
            'wallet_id' => $data['wallet_id'] ?? $data['wallet_code'] ?? null,
            'api_version' => $data['api_version'] ?? 'v2',
            'wallet_code' => $data['wallet_code'] ?? null,
            'debito_reference' => $data['debito_reference'] ?? null,
            'provider_payment_id' => $data['provider_payment_id'] ?? $data['debito_reference'] ?? null,
            'provider_reference' => $data['provider_reference'] ?? null,
            'request_payload_json' => $data['request_payload_json'] ?? null,
            'response_payload_json' => $data['response_payload_json'] ?? null,
            'provider_response_code' => $data['provider_response_code'] ?? null,
            'provider_response_message' => $data['provider_response_message'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ];

        $this->db->prepare($sql)->execute($payload);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatusByReference(string $reference, string $status, array $statusPayload): void
    {
        $s = $this->db->prepare('UPDATE debito_transactions SET status=:status,last_status_payload_json=:payload,last_checked_at=NOW(),updated_at=NOW() WHERE debito_reference=:ref');
        $s->execute(['status' => $status, 'payload' => json_encode($statusPayload, JSON_UNESCAPED_UNICODE), 'ref' => $reference]);
    }
}
