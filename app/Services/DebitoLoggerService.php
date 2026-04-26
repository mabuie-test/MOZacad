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
        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('c'),
            $level,
            $message,
            json_encode($safeContext, JSON_UNESCAPED_UNICODE)
        );

        file_put_contents($file, $line, FILE_APPEND);
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

        $rotated = $file . '.1';
        @unlink($rotated);
        @rename($file, $rotated);
    }
}
