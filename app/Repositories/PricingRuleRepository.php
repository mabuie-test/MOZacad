<?php

declare(strict_types=1);

namespace App\Repositories;

final class PricingRuleRepository extends BaseRepository
{
    public function all(int $limit = 300): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pricing_rules ORDER BY rule_code ASC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function upsert(string $ruleCode, string $ruleValue, ?string $description = null, bool $isActive = true): void
    {
        $stmt = $this->db->prepare('INSERT INTO pricing_rules (rule_code, rule_value, description, is_active, created_at, updated_at)
            VALUES (:rule_code, :rule_value, :description, :is_active, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                rule_value = VALUES(rule_value),
                description = VALUES(description),
                is_active = VALUES(is_active),
                updated_at = NOW()');

        $stmt->execute([
            'rule_code' => trim($ruleCode),
            'rule_value' => trim($ruleValue),
            'description' => $description !== null ? trim($description) : null,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }
}
