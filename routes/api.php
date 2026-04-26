<?php

declare(strict_types=1);

use App\Controllers\PaymentController;
use App\Helpers\Router;

return static function (Router $router): void {
    // Política API (JSON): endpoints /api/* são restritos a first-party web (sessão + header X-MOZACAD-CLIENT + verificação de origem + CSRF em verbos mutáveis).
    $router->post('/api/payments/mpesa/initiate', [PaymentController::class, 'initiateMpesa']);
    $router->get('/api/payments/{id}/status', [PaymentController::class, 'status']);
};
