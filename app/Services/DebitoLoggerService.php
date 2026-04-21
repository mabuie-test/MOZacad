<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class DebitoLoggerService
{
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $logsPath = trim((string) Env::get('STORAGE_LOGS_PATH', 'storage/logs'), '/');
        $path = dirname(__DIR__, 2) . '/' . $logsPath . '/debito.log';

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('c'),
            $level,
            $message,
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );
        file_put_contents($path, $line, FILE_APPEND);
    }
}
