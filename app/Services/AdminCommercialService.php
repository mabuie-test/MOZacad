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
        $payload = $this->discountPayloadFromRequest($input, true);
        if ($payload === null) {
            return null;
        }

        return (new UserDiscountRepository())->create($payload + [
            'created_by_admin_id' => $adminId,
        ]);
    }

    public function updateDiscount(int $id, array $input): bool
    {
        $payload = $this->discountPayloadFromRequest($input, false);
        if ($payload === null) {
            return false;
        }

        (new UserDiscountRepository())->update($id, $payload);
        return true;
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

    private function discountPayloadFromRequest(array $input, bool $requireUserId): ?array
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $type = trim((string) ($input['discount_type'] ?? ''));
        $value = (float) ($input['discount_value'] ?? -1);
        $workTypeId = !empty($input['work_type_id']) ? (int) $input['work_type_id'] : null;
        $extraCodeRaw = trim((string) ($input['extra_code'] ?? ''));
        $extraCode = $extraCodeRaw !== '' ? $extraCodeRaw : null;
        $usageLimit = !empty($input['usage_limit']) ? (int) $input['usage_limit'] : null;
        $startsAt = $this->normalizeDateTime($input['starts_at'] ?? null);
        $endsAt = $this->normalizeDateTime($input['ends_at'] ?? null);

        if ($requireUserId && $userId <= 0) {
            return null;
        }
        if (!in_array($type, ['percent', 'fixed', 'extra_waiver'], true)) {
            return null;
        }
        if ($value < 0) {
            return null;
        }
        if ($type === 'percent' && $value > 100) {
            return null;
        }
        if ($type === 'extra_waiver' && $extraCode === null) {
            return null;
        }
        if ($usageLimit !== null && $usageLimit <= 0) {
            return null;
        }
        if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) > strtotime($endsAt)) {
            return null;
        }

        $payload = [
            'name' => trim((string) ($input['name'] ?? 'Desconto personalizado')),
            'discount_type' => $type,
            'discount_value' => $value,
            'work_type_id' => $workTypeId,
            'extra_code' => $extraCode,
            'usage_limit' => $usageLimit,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
        ];

        if ($requireUserId) {
            $payload['user_id'] = $userId;
        }

        return $payload;
    }
}
