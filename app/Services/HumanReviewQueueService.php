<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;

final class HumanReviewQueueService
{
    public function enqueue(int $orderId, ?int $reviewerId = null): void
    {
        $sql = 'INSERT INTO human_review_queue (order_id, reviewer_id, status, created_at, updated_at)
                VALUES (:order_id,:reviewer_id,:status,NOW(),NOW())';
        Database::connect()->prepare($sql)->execute([
            'order_id' => $orderId,
            'reviewer_id' => $reviewerId,
            'status' => 'pending',
        ]);
    }
}
