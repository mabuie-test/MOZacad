<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;

final class RevisionService
{
    public function request(int $orderId, int $userId, string $reason): void
    {
        $sql = 'INSERT INTO revisions (order_id, user_id, reason, status, created_at, updated_at)
                VALUES (:order_id,:user_id,:reason,:status,NOW(),NOW())';
        Database::connect()->prepare($sql)->execute([
            'order_id' => $orderId,
            'user_id' => $userId,
            'reason' => $reason,
            'status' => 'requested',
        ]);
    }
}
