<?php

declare(strict_types=1);

use App\Controllers\PaymentController;
use App\Helpers\Router;

return static function (Router $router): void {
    // Contrato API (JSON): endpoints sob /api/* para separar da interface web.
    $router->post('/api/payments/mpesa/initiate', [PaymentController::class, 'initiateMpesa']);
    $router->get('/api/payments/{id}/status', [PaymentController::class, 'status']);
};
