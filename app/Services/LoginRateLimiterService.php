<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LoginAttemptRepository;

final class LoginRateLimiterService
{
    public function __construct(private readonly LoginAttemptRepository $attempts = new LoginAttemptRepository()) {}

    /** @return array{allowed:bool,retry_after:int} */
    public function check(string $email, string $ipAddress): array
    {
        $row = $this->attempts->findByEmailAndIp($email, $ipAddress);
        if ($row === null) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $lockedUntil = (string) ($row['locked_until'] ?? '');
        if ($lockedUntil === '') {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $ts = strtotime($lockedUntil);
        if ($ts === false || $ts <= time()) {
            $lastFailedAt = strtotime((string) ($row['last_failed_at'] ?? ''));
            if ($lastFailedAt !== false && $lastFailedAt < (time() - 86400)) {
                $this->attempts->clear($email, $ipAddress);
            }
            return ['allowed' => true, 'retry_after' => 0];
        }

        return ['allowed' => false, 'retry_after' => max(1, $ts - time())];
    }

    public function onFailure(string $email, string $ipAddress): void
    {
        $current = $this->attempts->findByEmailAndIp($email, $ipAddress);
        $lastFailedAt = strtotime((string) ($current['last_failed_at'] ?? ''));
        $windowExpired = $lastFailedAt === false || $lastFailedAt < (time() - 1800);
        $baseCount = $windowExpired ? 0 : (int) ($current['failed_attempts'] ?? 0);
        $failCount = $baseCount + 1;

        $lockSeconds = match (true) {
            $failCount >= 8 => 3600,
            $failCount >= 6 => 900,
            $failCount >= 4 => 300,
            $failCount >= 3 => 60,
            default => 0,
        };

        $this->attempts->upsertFailure($email, $ipAddress, $failCount, $lockSeconds);
    }

    public function onSuccess(string $email, string $ipAddress): void
    {
        $this->attempts->clear($email, $ipAddress);
    }
}
