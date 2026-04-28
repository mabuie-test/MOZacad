<?php

declare(strict_types=1);

namespace App\Repositories;

final class RoleRepository extends BaseRepository
{
    public function all(): array
    {
        $stmt = $this->db->query('SELECT id, name, description FROM roles ORDER BY name ASC');
        return $stmt ? $stmt->fetchAll() : [];
    }
}
