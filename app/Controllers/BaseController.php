<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;

abstract class BaseController
{
    protected function view(string $template, array $data = []): void
    {
        View::render($template, $data);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
