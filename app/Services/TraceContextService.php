<?php

declare(strict_types=1);

namespace App\Services;

final class TraceContextService
{
    private const SERVER_KEY = 'MOZACAD_TRACE_ID';

    public function currentTraceId(array $server = []): string
    {
        $source = $server !== [] ? $server : $_SERVER;
        $existing = trim((string) ($source[self::SERVER_KEY] ?? $_SERVER[self::SERVER_KEY] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $incoming = trim((string) (
            $source['HTTP_X_REQUEST_ID']
            ?? $source['HTTP_X_CORRELATION_ID']
            ?? $source['UNIQUE_ID']
            ?? ''
        ));

        $traceId = $this->normalizeTraceId($incoming);
        if ($traceId === '') {
            $traceId = bin2hex(random_bytes(16));
        }

        $_SERVER[self::SERVER_KEY] = $traceId;
        return $traceId;
    }

    private function normalizeTraceId(string $candidate): string
    {
        $trimmed = trim($candidate);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $trimmed) !== 1) {
            return '';
        }

        return $trimmed;
    }
}
