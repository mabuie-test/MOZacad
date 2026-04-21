<?php

declare(strict_types=1);

namespace App\Repositories;

final class OrderRepository extends BaseRepository
{
    public function listAll(int $limit = 200): array
    {
        $stmt = $this->db->prepare('SELECT o.*, u.email AS user_email, wt.name AS work_type_name
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            INNER JOIN work_types wt ON wt.id = o.work_type_id
            ORDER BY o.created_at DESC
            LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT o.*, wt.name AS work_type_name, i.name AS institution_name FROM orders o
            INNER JOIN work_types wt ON wt.id = o.work_type_id
            INNER JOIN institutions i ON i.id = o.institution_id
            WHERE o.user_id = :user_id ORDER BY o.created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO orders (user_id, institution_id, course_id, discipline_id, academic_level_id, work_type_id, title_or_theme,
                subtitle, problem_statement, general_objective, specific_objectives_json, hypothesis, keywords_json, target_pages,
                complexity_level, deadline_date, notes, status, final_price, created_at, updated_at)
                VALUES (:user_id, :institution_id, :course_id, :discipline_id, :academic_level_id, :work_type_id, :title_or_theme,
                :subtitle, :problem_statement, :general_objective, :specific_objectives_json, :hypothesis, :keywords_json, :target_pages,
                :complexity_level, :deadline_date, :notes, :status, :final_price, NOW(), NOW())';

        $this->db->prepare($sql)->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function createRequirement(array $data): void
    {
        $sql = 'INSERT INTO order_requirements (order_id, needs_institution_cover, needs_abstract, needs_bilingual_abstract,
                needs_methodology_review, needs_humanized_revision, needs_slides, needs_defense_summary, notes, created_at, updated_at)
                VALUES (:order_id, :needs_institution_cover, :needs_abstract, :needs_bilingual_abstract,
                :needs_methodology_review, :needs_humanized_revision, :needs_slides, :needs_defense_summary, :notes, NOW(), NOW())';
        $this->db->prepare($sql)->execute($data);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function findDetailedById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT o.*, wt.slug AS work_type_slug, wt.requires_human_review,
                al.slug AS academic_level_slug, al.multiplier AS academic_level_multiplier,
                req.needs_humanized_revision, req.needs_methodology_review, req.needs_bilingual_abstract,
                req.needs_slides, req.needs_defense_summary, req.needs_institution_cover
            FROM orders o
            INNER JOIN work_types wt ON wt.id = o.work_type_id
            INNER JOIN academic_levels al ON al.id = o.academic_level_id
            LEFT JOIN order_requirements req ON req.order_id = o.id
            WHERE o.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function updateFinalPrice(int $id, float $finalPrice): void
    {
        $stmt = $this->db->prepare('UPDATE orders SET final_price = :final_price, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['final_price' => $finalPrice, 'id' => $id]);
    }
}
