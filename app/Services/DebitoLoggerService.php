<?php

declare(strict_types=1);

namespace App\Services;

final class DebitoLoggerService
{
    public function info(string $message, array $context = []): void
    {
        $line = sprintf("[%s] %s %s\n", date('c'), $message, json_encode($context, JSON_UNESCAPED_UNICODE));
        file_put_contents(__DIR__ . '/../../storage/logs/debito.log', $line, FILE_APPEND);
    }
}
