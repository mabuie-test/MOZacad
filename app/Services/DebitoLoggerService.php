<?php

declare(strict_types=1);

namespace App\Services;

final class DebitoLoggerService
{
    public function __construct(private readonly StoragePathService $paths = new StoragePathService()) {}

    public function info(string $message, array $context = []): void { $this->write('INFO', $message, $context); }
    public function error(string $message, array $context = []): void { $this->write('ERROR', $message, $context); }

    private function write(string $level, string $message, array $context): void
    {
        $base = $this->paths->logsBase();
        $this->paths->ensureDirectory($base);
        file_put_contents($base . '/debito.log', sprintf("[%s] [%s] %s %s\n", date('c'), $level, $message, json_encode($context, JSON_UNESCAPED_UNICODE)), FILE_APPEND);
    }
}
