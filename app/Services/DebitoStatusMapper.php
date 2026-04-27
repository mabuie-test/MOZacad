<?php

declare(strict_types=1);

namespace App\Services;

final class DebitoStatusMapper
{
    public function map(string $providerStatus): string
    {
        $normalized = $this->normalize($providerStatus);

        return match ($normalized) {
            'pending', 'created', 'queued' => 'pending',
            'processing', 'inprogress', 'authorizing' => 'processing',
            'waitingconfirmation', 'awaitingconfirmation' => 'pending_confirmation',
            'confirmed', 'confirmado', 'confirmada', 'approved', 'aprovado', 'aprovada' => 'paid',
            'paid', 'success', 'successful', 'completed', 'complete', 'concluido', 'concluído' => 'paid',
            'transactionsuccessful', 'completedsuccessfully' => 'paid',
            'failed', 'error', 'declined', 'denied', 'rejected', 'unsuccessful' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'expired', 'timeout', 'timedout' => 'expired',
            default => $this->fallbackStateForUnknown($normalized),
        };
    }

    private function normalize(string $providerStatus): string
    {
        $normalized = strtolower(trim($providerStatus));
        $normalized = str_replace([' ', '-', '_'], '', $normalized);
        return $normalized;
    }

    private function fallbackStateForUnknown(string $providerStatus): string
    {
        if (str_contains($providerStatus, 'unsuccess')) {
            return 'failed';
        }

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
            || str_contains($providerStatus, 'conclu')
            || str_contains($providerStatus, 'confirm')
            || str_contains($providerStatus, 'approv')
            || str_contains($providerStatus, 'aprov')) {
            return 'paid';
        }

        return 'pending_confirmation';
    }
}
