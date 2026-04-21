<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;

final class DiscountUsageLoggerService
{
    public function log(int $discountId, int $userId, int $orderId, float $amount, array $details = []): void
    {
        $sql = 'INSERT INTO discount_usage_logs (user_discount_id, user_id, order_id, amount_discounted, details_json, created_at)
                VALUES (:user_discount_id,:user_id,:order_id,:amount_discounted,:details_json,NOW())';
        Database::connect()->prepare($sql)->execute([
            'user_discount_id' => $discountId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'amount_discounted' => $amount,
            'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
