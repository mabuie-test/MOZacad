<?php

declare(strict_types=1);

namespace App\Services;

interface PaymentProviderInterface
{
    public function initiate(array $payload): array;
    public function checkStatus(string $reference): array;
}
