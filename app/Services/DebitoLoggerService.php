<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class DebitoLoggerService
{
    public function __construct(
        private readonly StoragePathService $paths = new StoragePathService(),
        private readonly LogSanitizerService $sanitizer = new LogSanitizerService(),
    ) {}

    public function info(string $message, array $context = []): void { $this->write('INFO', $message, $context); }
    public function error(string $message, array $context = []): void { $this->write('ERROR', $message, $context); }

    private function write(string $level, string $message, array $context): void
    {
        $base = $this->paths->logsBase();
        $this->paths->ensureDirectory($base);

        $file = $base . '/debito.log';
        $this->rotateIfNeeded($file);

        $safeContext = $this->sanitizer->sanitize($context);
        $safeContext['trace_id'] = (new TraceContextService())->currentTraceId($_SERVER);
        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('c'),
            $level,
            $message,
            json_encode($safeContext, JSON_UNESCAPED_UNICODE)
        );

        file_put_contents($file, $line, FILE_APPEND);
        $this->enforcePermissions($file);
    }

    private function rotateIfNeeded(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $maxMb = max(1, (int) Env::get('LOG_MAX_FILE_SIZE_MB', 20));
        if (filesize($file) <= ($maxMb * 1024 * 1024)) {
            return;
        }

        $maxRotatedFiles = max(1, (int) Env::get('LOG_MAX_ROTATED_FILES', 5));
        $overflow = $file . '.' . ($maxRotatedFiles + 1);
        if (is_file($overflow)) {
            @unlink($overflow);
        }
        for ($i = $maxRotatedFiles; $i >= 1; $i--) {
            $from = $file . '.' . $i;
            $to = $file . '.' . ($i + 1);
            if (is_file($from)) {
                @rename($from, $to);
            }
        }
        @rename($file, $file . '.1');
        $this->enforcePermissions($file . '.1');
    }

    private function enforcePermissions(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $mode = trim((string) Env::get('LOG_FILE_PERMISSIONS', '0600'));
        if (!preg_match('/^0?[0-7]{3,4}$/', $mode)) {
            return;
        }

        $normalized = ltrim($mode, '0');
        if ($normalized === '') {
            $normalized = '600';
        }

        @chmod($file, octdec($normalized));
    }
}
