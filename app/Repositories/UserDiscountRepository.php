<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserDiscountRepository extends BaseRepository
{
    public function listAll(int $limit = 200): array
    {
        $stmt = $this->db->prepare('SELECT ud.*, u.email AS user_email, wt.name AS work_type_name
            FROM user_discounts ud
            INNER JOIN users u ON u.id = ud.user_id
            LEFT JOIN work_types wt ON wt.id = ud.work_type_id
            ORDER BY ud.created_at DESC
            LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findEligible(int $userId, ?int $workTypeId = null): array
    {
        $sql = "SELECT * FROM user_discounts WHERE user_id=:user_id AND is_active=1
                AND (starts_at IS NULL OR starts_at <= NOW())
                AND (ends_at IS NULL OR ends_at >= NOW())
                AND (usage_limit IS NULL OR used_count < usage_limit)
                AND (work_type_id IS NULL OR work_type_id = :work_type_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'work_type_id' => $workTypeId]);
        return $stmt->fetchAll();
    }

    public function incrementUsage(int $discountId): void
    {
        $stmt = $this->db->prepare('UPDATE user_discounts SET used_count = used_count + 1, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $discountId]);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO user_discounts
            (user_id, name, discount_type, discount_value, work_type_id, extra_code, usage_limit, used_count, starts_at, ends_at, is_active, created_by_admin_id, notes, created_at, updated_at)
            VALUES
            (:user_id, :name, :discount_type, :discount_value, :work_type_id, :extra_code, :usage_limit, 0, :starts_at, :ends_at, :is_active, :created_by_admin_id, :notes, NOW(), NOW())');
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = $this->db->prepare('UPDATE user_discounts SET
            name = :name,
            discount_type = :discount_type,
            discount_value = :discount_value,
            work_type_id = :work_type_id,
            extra_code = :extra_code,
            usage_limit = :usage_limit,
            starts_at = :starts_at,
            ends_at = :ends_at,
            is_active = :is_active,
            notes = :notes,
            updated_at = NOW()
            WHERE id = :id');
        $stmt->execute($data);
    }
}
