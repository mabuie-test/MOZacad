<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use RuntimeException;

final class StoragePathService
{
    public function uploadsBase(): string
    {
        return $this->resolveConfiguredBase('STORAGE_UPLOADS_PATH', 'storage/uploads');
    }

    public function generatedBase(): string
    {
        return $this->resolveConfiguredBase('STORAGE_GENERATED_PATH', 'storage/generated');
    }

    public function logsBase(): string
    {
        return $this->resolveConfiguredBase('STORAGE_LOGS_PATH', 'storage/logs');
    }

    public function normsBase(): string
    {
        return $this->resolveConfiguredBase('STORAGE_NORMS_PATH', 'storage/norms');
    }

    public function templatesBase(): string
    {
        return $this->resolveConfiguredBase('STORAGE_TEMPLATES_PATH', 'storage/templates');
    }

    public function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Falha ao criar directório no storage.');
        }
    }

    public function ensurePathInside(string $candidatePath, string $basePath): string
    {
        $baseReal = realpath($basePath);
        $resolved = realpath($candidatePath);

        if ($baseReal === false || $resolved === false || !str_starts_with($resolved, $baseReal . DIRECTORY_SEPARATOR)) {
            if ($resolved !== $baseReal) {
                throw new RuntimeException('Path fora do storage permitido.');
            }
        }

        return $resolved;
    }

    private function resolveConfiguredBase(string $key, string $default): string
    {
        $configured = trim((string) Env::get($key, $default));
        if ($configured === '') {
            $configured = $default;
        }

        $root = dirname(__DIR__, 2);
        $base = str_starts_with($configured, '/') ? $configured : ($root . '/' . trim($configured, '/'));

        return rtrim($base, '/');
    }
}
