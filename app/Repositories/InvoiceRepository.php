<?php
declare(strict_types=1);
namespace App\Repositories;
final class InvoiceRepository extends BaseRepository
{
    public function markPaidById(int $id): void { $s=$this->db->prepare("UPDATE invoices SET status='paid', paid_at=NOW(), updated_at=NOW() WHERE id=:id"); $s->execute(['id'=>$id]); }
}
