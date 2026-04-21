<?php

declare(strict_types=1);

namespace App\Services;

final class DebitoStatusMapper
{
    public function map(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'pending' => 'pending',
            'processing', 'in_progress' => 'processing',
            'waiting_confirmation' => 'pending_confirmation',
            'paid', 'success', 'completed' => 'paid',
            'failed', 'error' => 'failed',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            default => 'pending_confirmation',
        };
    }
}
