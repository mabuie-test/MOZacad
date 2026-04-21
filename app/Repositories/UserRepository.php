<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, phone, password_hash, institution_id, course_id, discipline_id, is_active, created_at, updated_at)
            VALUES (:name, :email, :phone, :password_hash, :institution_id, :course_id, :discipline_id, :is_active, NOW(), NOW())');

        $stmt->execute([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'phone' => $data['phone'] ?? null,
            'password_hash' => $data['password_hash'],
            'institution_id' => $data['institution_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'discipline_id' => $data['discipline_id'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function all(int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function listByRole(string $roleName, int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT u.*
            FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE r.name = :role_name
            ORDER BY u.created_at DESC
            LIMIT :limit');
        $stmt->bindValue('role_name', trim($roleName));
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
