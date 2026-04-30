<?php

declare(strict_types=1);

use App\Helpers\Csrf;

if (!function_exists('csrf_field')) {
    function csrf_field(?string $token = null): string
    {
        $value = htmlspecialchars((string) ($token ?? Csrf::token()), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $value . '">';
    }
}
