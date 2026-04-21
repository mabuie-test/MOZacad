<?php
declare(strict_types=1);
namespace App\Repositories;
final class GeneratedDocumentRepository extends BaseRepository
{
    public function create(int $orderId, string $path, string $status='generated', int $version=1): int
    {
        $s=$this->db->prepare('INSERT INTO generated_documents (order_id,file_path,status,version,created_at) VALUES (:order_id,:file_path,:status,:version,NOW())');
        $s->execute(['order_id'=>$orderId,'file_path'=>$path,'status'=>$status,'version'=>$version]);
        return (int)$this->db->lastInsertId();
    }
}
