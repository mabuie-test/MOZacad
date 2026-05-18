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
        $stmt = $this->db->prepare('SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ",") AS role_names
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            GROUP BY u.id
            ORDER BY u.created_at DESC
            LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function updateStatus(int $userId, bool $isActive): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :is_active, updated_at = NOW() WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId, 'is_active' => $isActive ? 1 : 0]);
    }

    public function deleteById(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
    }

    public function syncRoles(int $userId, array $roleNames): void
    {
        $roleNames = array_values(array_filter(array_map(static fn (mixed $n): string => trim((string) $n), $roleNames), static fn (string $n): bool => $n !== ''));
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);

            if ($roleNames !== []) {
                $placeholders = [];
                $params = [];
                foreach ($roleNames as $i => $roleName) {
                    $key = 'role_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $roleName;
                }
                $stmt = $this->db->prepare('SELECT id, name FROM roles WHERE name IN (' . implode(',', $placeholders) . ')');
                $stmt->execute($params);
                $roles = $stmt->fetchAll();
                $insert = $this->db->prepare('INSERT INTO user_roles (user_id, role_id, created_at, updated_at) VALUES (:user_id, :role_id, NOW(), NOW())');
                foreach ($roles as $role) {
                    $insert->execute(['user_id' => $userId, 'role_id' => (int) ($role['id'] ?? 0)]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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
    public function hasAnyRole(int $userId, array $roleNames): bool
    {
        if ($userId <= 0 || $roleNames === []) {
            return false;
        }

        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach (array_values($roleNames) as $index => $roleName) {
            $key = 'role_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = trim((string) $roleName);
        }

        $stmt = $this->db->prepare('SELECT 1
            FROM user_roles ur
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
              AND r.name IN (' . implode(',', $placeholders) . ')
            LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

}
