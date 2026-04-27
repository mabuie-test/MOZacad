<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class ApplicationLoggerService
{
    public function __construct(
        private readonly StoragePathService $paths = new StoragePathService(),
        private readonly LogSanitizerService $sanitizer = new LogSanitizerService(),
    ) {}

    public function info(string $event, array $context = []): void { $this->write('INFO', $event, $context); }
    public function error(string $event, array $context = []): void { $this->write('ERROR', $event, $context); }
    public function alert(string $event, array $context = []): void { $this->write('ALERT', $event, $context); }

    private function write(string $level, string $event, array $context): void
    {
        $base = $this->paths->logsBase();
        $this->paths->ensureDirectory($base);

        $file = $base . '/application.log';
        $this->rotateIfNeeded($file);

        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('c'),
            $level,
            $event,
            json_encode($this->sanitizer->sanitize($context + ['trace_id' => (new TraceContextService())->currentTraceId($_SERVER)]), JSON_UNESCAPED_UNICODE)
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
