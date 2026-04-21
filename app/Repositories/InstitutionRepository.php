<?php
declare(strict_types=1);
namespace App\Repositories;
final class InstitutionRepository extends BaseRepository { public function all(): array { return $this->db->query('SELECT * FROM institutions WHERE is_active=1 ORDER BY name')->fetchAll(); } }
