<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CouponRepository;
use App\Repositories\UserDiscountRepository;

final class AdminCommercialService
{
    public function couponPayloadFromRequest(array $input): ?array
    {
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $discountType = trim((string) ($input['discount_type'] ?? ''));
        $discountValue = (float) ($input['discount_value'] ?? -1);
        $usageLimitRaw = trim((string) ($input['usage_limit'] ?? ''));
        $startsAt = $this->normalizeDateTime($input['starts_at'] ?? null);
        $endsAt = $this->normalizeDateTime($input['ends_at'] ?? null);

        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) return null;
        if (!in_array($discountType, ['percent', 'fixed'], true) || $discountValue < 0) return null;
        if ($discountType === 'percent' && $discountValue > 100) return null;

        $usageLimit = $usageLimitRaw === '' ? null : (int) $usageLimitRaw;
        if ($usageLimit !== null && $usageLimit <= 0) return null;
        if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) > strtotime($endsAt)) return null;

        return [
            'code' => $code,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'usage_limit' => $usageLimit,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];
    }

    public function createDiscount(array $input, int $adminId): ?int
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $type = (string) ($input['discount_type'] ?? '');
        $value = (float) ($input['discount_value'] ?? 0);
        if ($userId <= 0 || !in_array($type, ['percent', 'fixed', 'extra_waiver'], true) || $value < 0) {
            return null;
        }

        return (new UserDiscountRepository())->create([
            'user_id' => $userId,
            'name' => trim((string) ($input['name'] ?? 'Desconto personalizado')),
            'discount_type' => $type,
            'discount_value' => $value,
            'work_type_id' => !empty($input['work_type_id']) ? (int) $input['work_type_id'] : null,
            'extra_code' => $input['extra_code'] ?? null,
            'usage_limit' => !empty($input['usage_limit']) ? (int) $input['usage_limit'] : null,
            'starts_at' => $input['starts_at'] ?? null,
            'ends_at' => $input['ends_at'] ?? null,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'created_by_admin_id' => $adminId,
            'notes' => $input['notes'] ?? null,
        ]);
    }

    public function updateDiscount(int $id, array $input): void
    {
        (new UserDiscountRepository())->update($id, [
            'name' => trim((string) ($input['name'] ?? 'Desconto personalizado')),
            'discount_type' => (string) ($input['discount_type'] ?? 'fixed'),
            'discount_value' => (float) ($input['discount_value'] ?? 0),
            'work_type_id' => !empty($input['work_type_id']) ? (int) $input['work_type_id'] : null,
            'extra_code' => $input['extra_code'] ?? null,
            'usage_limit' => !empty($input['usage_limit']) ? (int) $input['usage_limit'] : null,
            'starts_at' => $input['starts_at'] ?? null,
            'ends_at' => $input['ends_at'] ?? null,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'notes' => $input['notes'] ?? null,
        ]);
    }

    public function createCoupon(array $payload): ?int
    {
        $repo = new CouponRepository();
        if ($repo->findActiveByCode($payload['code']) !== null) {
            return null;
        }

        return $repo->create($payload);
    }

    public function updateCoupon(int $id, array $payload): bool
    {
        $repo = new CouponRepository();
        if ($repo->findById($id) === null) {
            return false;
        }

        $repo->update($id, $payload);
        return true;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
