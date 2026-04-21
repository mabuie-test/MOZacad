<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RevisionRepository;

final class RevisionService
{
    public function __construct(private readonly RevisionRepository $revisions = new RevisionRepository()) {}

    public function request(int $orderId, int $userId, string $reason): int
    {
        return $this->revisions->create($orderId, $userId, trim($reason), 'requested');
    }
}
