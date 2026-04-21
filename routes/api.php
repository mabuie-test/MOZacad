<?php

declare(strict_types=1);

use App\Controllers\DebitoWebhookController;
use App\Controllers\PaymentController;
use App\Helpers\Router;

return static function (Router $router): void {
    $router->post('/payments/mpesa/initiate', [PaymentController::class, 'initiateMpesa']);
    $router->get('/payments/{id}/status', [PaymentController::class, 'status']);
    $router->post('/webhooks/debito', [DebitoWebhookController::class, 'handle']);
};
