<?php

declare(strict_types=1);

namespace App\Services;

final class LogSanitizerService
{
    /** @var array<int,string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'token',
        'secret',
        'password',
        'api_key',
        'access_key',
        'refresh_token',
        'x_debito_signature',
        'x_webhook_signature',
        'signature',
        'msisdn',
        'phone',
    ];

    public function sanitize(array $context): array
    {
        return $this->sanitizeValue($context, 'context');
    }

    private function sanitizeValue(mixed $value, string $key): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $itemKey => $itemValue) {
                $normalizedKey = is_string($itemKey) ? $itemKey : (string) $itemKey;
                $result[$itemKey] = $this->sanitizeValue($itemValue, $normalizedKey);
            }

            return $result;
        }

        if (!is_scalar($value) && $value !== null) {
            return '[non_scalar_redacted]';
        }

        $keyLower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($keyLower, $sensitive)) {
                return $this->maskSensitiveScalar($value, $keyLower);
            }
        }

        if (is_string($value) && strlen($value) > 2048) {
            return mb_substr($value, 0, 256) . '...[truncated]';
        }

        return $value;
    }

    private function maskSensitiveScalar(mixed $value, string $key): string
    {
        if (!is_scalar($value) || $value === null) {
            return '[redacted]';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '[redacted]';
        }

        if (str_contains($key, 'msisdn')) {
            $digits = preg_replace('/\D+/', '', $raw) ?? '';
            if ($digits === '') {
                return '[redacted_msisdn]';
            }

            $prefix = mb_substr($digits, 0, 2);
            $suffix = mb_substr($digits, -2);
            return sprintf('%s*****%s', $prefix, $suffix);
        }

        return '[redacted]';
    }
}
