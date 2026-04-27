<?php

declare(strict_types=1);

namespace App\Repositories;

final class WebhookReplayRepository extends BaseRepository
{
    public function purgeExpired(string $provider): void
    {
        $stmt = $this->db->prepare('DELETE FROM webhook_replay_events WHERE provider = :provider AND expires_at IS NOT NULL AND expires_at < NOW()');
        $stmt->execute(['provider' => $provider]);
    }

    public function registerEvent(string $provider, string $eventKey, string $signatureHash, string $payloadHash, int $ttlSeconds, ?string $eventTimestamp = null): array
    {
        $ttlSeconds = max(60, min(604800, $ttlSeconds));
        $stmt = $this->db->prepare('INSERT INTO webhook_replay_events (
                provider, event_key, signature_hash, payload_hash, event_timestamp, received_at, first_seen_at, last_seen_at, hit_count, expires_at, created_at
            ) VALUES (
                :provider, :event_key, :signature_hash, :payload_hash, :event_timestamp, NOW(), NOW(), NOW(), 1, DATE_ADD(NOW(), INTERVAL :ttl_seconds SECOND), NOW()
            )
            ON DUPLICATE KEY UPDATE
                last_seen_at = NOW(),
                hit_count = hit_count + 1,
                expires_at = DATE_ADD(NOW(), INTERVAL :ttl_seconds SECOND)');
        $stmt->bindValue('provider', $provider);
        $stmt->bindValue('event_key', $eventKey);
        $stmt->bindValue('signature_hash', $signatureHash);
        $stmt->bindValue('payload_hash', $payloadHash);
        $stmt->bindValue('event_timestamp', $eventTimestamp);
        $stmt->bindValue('ttl_seconds', $ttlSeconds, \PDO::PARAM_INT);
        $stmt->execute();

        $lookup = $this->db->prepare('SELECT id, hit_count FROM webhook_replay_events WHERE provider = :provider AND event_key = :event_key LIMIT 1');
        $lookup->execute([
            'provider' => $provider,
            'event_key' => $eventKey,
        ]);
        $row = $lookup->fetch();
        $hitCount = (int) ($row['hit_count'] ?? 0);

        return [
            'accepted' => $hitCount === 1,
            'hit_count' => $hitCount,
            'event_id' => (int) ($row['id'] ?? 0),
        ];
    }

    public function release(string $provider, string $eventKey): void
    {
        $stmt = $this->db->prepare('DELETE FROM webhook_replay_events WHERE provider = :provider AND event_key = :event_key');
        $stmt->execute([
            'provider' => $provider,
            'event_key' => $eventKey,
        ]);
    }
}
