<?php

declare(strict_types=1);

namespace App\Services;

final class ApplicationLoggerService
{
    public function __construct(private readonly StoragePathService $paths = new StoragePathService()) {}

    public function info(string $event, array $context = []): void
    {
        $this->write('INFO', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->write('ERROR', $event, $context);
    }

    private function write(string $level, string $event, array $context): void
    {
        $base = $this->paths->logsBase();
        $this->paths->ensureDirectory($base);

        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('c'),
            $level,
            $event,
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );

        file_put_contents($base . '/application.log', $line, FILE_APPEND);
    }
}
