<?php

declare(strict_types=1);

namespace App\Services;

final class DebitoStatusMapper
{
    public function map(string $providerStatus): string
    {
        $normalized = strtolower(trim($providerStatus));

        return match ($normalized) {
            'pending', 'created', 'queued' => 'pending',
            'processing', 'in_progress', 'authorizing' => 'processing',
            'waiting_confirmation', 'awaiting_confirmation' => 'pending_confirmation',
            'paid', 'success', 'successful', 'completed', 'complete', 'concluido', 'concluído' => 'paid',
            'failed', 'error', 'declined', 'denied', 'rejected' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'expired', 'timeout', 'timed_out' => 'expired',
            default => $this->fallbackStateForUnknown($normalized),
        };
    }

    private function fallbackStateForUnknown(string $providerStatus): string
    {
        if (str_contains($providerStatus, 'fail') || str_contains($providerStatus, 'error')) {
            return 'failed';
        }

        if (str_contains($providerStatus, 'cancel')) {
            return 'cancelled';
        }

        if (str_contains($providerStatus, 'expir')) {
            return 'expired';
        }

        if (str_contains($providerStatus, 'succes')
            || str_contains($providerStatus, 'complet')
            || str_contains($providerStatus, 'conclu')) {
            return 'paid';
        }

        return 'pending_confirmation';
    }
}
