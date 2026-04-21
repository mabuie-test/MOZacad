<?php

declare(strict_types=1);

namespace App\Services;

final class DebitoStatusMapper
{
    public function map(string $providerStatus): string
    {
        return match (strtolower(trim($providerStatus))) {
            'pending', 'created', 'queued' => 'pending',
            'processing', 'in_progress', 'authorizing' => 'processing',
            'waiting_confirmation', 'awaiting_confirmation' => 'pending_confirmation',
            'paid', 'success', 'successful', 'completed' => 'paid',
            'failed', 'error', 'declined' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'expired', 'timeout' => 'expired',
            default => 'pending_confirmation',
        };
    }
}
