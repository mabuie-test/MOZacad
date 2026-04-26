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
        $normalized = $this->normalizeAbsolutePath($path);

        if (is_dir($normalized)) {
            return;
        }

        $mode = $this->resolveDirectoryMode();
        if (!mkdir($normalized, $mode, true) && !is_dir($normalized)) {
            throw new RuntimeException('Falha ao criar directório no storage.');
        }

        @chmod($normalized, $mode);
    }

    public function ensurePathInside(string $candidatePath, string $basePath): string
    {
        $base = rtrim($this->normalizeAbsolutePath($basePath), DIRECTORY_SEPARATOR);
        $candidate = $this->normalizeAbsolutePath($candidatePath);

        if ($candidate !== $base && !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Path fora do storage permitido.');
        }

        return $candidate;
    }

    private function resolveConfiguredBase(string $key, string $default): string
    {
        $configured = trim((string) Env::get($key, $default));
        if ($configured === '') {
            $configured = $default;
        }

        $root = dirname(__DIR__, 2);
        $base = str_starts_with($configured, DIRECTORY_SEPARATOR)
            ? $configured
            : ($root . DIRECTORY_SEPARATOR . trim($configured, '/'));

        $normalized = $this->normalizeAbsolutePath($base);
        $this->assertNotInWebRoot($normalized, $root);

        return $normalized;
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new RuntimeException('Path inválido para storage.');
        }

        $real = realpath($trimmed);
        if ($real !== false) {
            return rtrim($real, DIRECTORY_SEPARATOR);
        }

        $absolute = str_starts_with($trimmed, DIRECTORY_SEPARATOR)
            ? $trimmed
            : (getcwd() . DIRECTORY_SEPARATOR . $trimmed);

        return $this->collapsePath($absolute);
    }

    private function collapsePath(string $path): string
    {
        $segments = preg_split('#[\/]+#', $path) ?: [];
        $stack = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($stack);
                continue;
            }
            $stack[] = $segment;
        }

        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $stack);
    }

    private function resolveDirectoryMode(): int
    {
        $configured = trim((string) Env::get('STORAGE_DIR_MODE', ''));
        if ($configured !== '' && preg_match('/^0?[0-7]{3,4}$/', $configured) === 1) {
            return intval($configured, 8);
        }

        $currentUmask = umask();
        umask($currentUmask);

        $mode = 0777 & (~$currentUmask);
        return ($mode >= 0700 && $mode <= 0777) ? $mode : 0770;
    }

    private function assertNotInWebRoot(string $path, string $projectRoot): void
    {
        $publicRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public';
        $pathInPublic = $path === $publicRoot || str_starts_with($path, $publicRoot . DIRECTORY_SEPARATOR);

        if (!$pathInPublic) {
            return;
        }

        $isProduction = strtolower(trim((string) Env::get('APP_ENV', 'production'))) === 'production';
        if ($isProduction) {
            throw new RuntimeException('Storage em produção não pode apontar para o webroot/public.');
        }
    }
}
