<?php
declare(strict_types=1);
namespace App\Repositories;

use PDOException;

final class AcademicLevelRepository extends BaseRepository
{
    public function all(): array
    {
        try {
            return $this->db
                ->query('SELECT * FROM academic_levels ORDER BY display_order ASC, id ASC')
                ->fetchAll();
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? null) !== '42S22') {
                throw $e;
            }

            return $this->db
                ->query('SELECT * FROM academic_levels ORDER BY id ASC')
                ->fetchAll();
        }
    }

    public function findById(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM academic_levels WHERE id=:id LIMIT 1');
        $s->execute(['id' => $id]);

        return $s->fetch() ?: null;
    }
}
