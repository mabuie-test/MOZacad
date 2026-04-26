<?php

declare(strict_types=1);

namespace App\Repositories;

final class LoginAttemptRepository extends BaseRepository
{
    public function findByEmailAndIp(string $email, string $ipAddress): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM auth_login_attempts WHERE email = :email AND ip_address = :ip_address LIMIT 1');
        $stmt->execute(['email' => $email, 'ip_address' => $ipAddress]);
        return $stmt->fetch() ?: null;
    }

    public function upsertFailure(string $email, string $ipAddress, int $failedAttempts, int $lockSeconds): void
    {
        $sql = 'INSERT INTO auth_login_attempts (email, ip_address, failed_attempts, last_failed_at, locked_until, created_at, updated_at)
                VALUES (:email, :ip_address, :failed_attempts, NOW(), :locked_until, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    failed_attempts = :failed_attempts,
                    last_failed_at = NOW(),
                    locked_until = :locked_until,
                    updated_at = NOW()';
        $lockedUntil = $lockSeconds > 0 ? date('Y-m-d H:i:s', time() + $lockSeconds) : null;
        $this->db->prepare($sql)->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
            'failed_attempts' => $failedAttempts,
            'locked_until' => $lockedUntil,
        ]);
    }

    public function clear(string $email, string $ipAddress): void
    {
        $stmt = $this->db->prepare('DELETE FROM auth_login_attempts WHERE email = :email AND ip_address = :ip_address');
        $stmt->execute(['email' => $email, 'ip_address' => $ipAddress]);
    }
}
