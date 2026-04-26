<?php

declare(strict_types=1);

use App\Controllers\PaymentController;
use App\Helpers\Router;
use App\Services\HttpRoutePolicyService;

return static function (Router $router): void {
    $policy = new HttpRoutePolicyService();
    $api = fn (callable $next) => $policy->enforceFirstPartyApi($next);

    $router->post('/api/payments/mpesa/initiate', [PaymentController::class, 'initiateMpesa'], [$api]);
    $router->get('/api/payments/{id}/status', [PaymentController::class, 'status'], [$api]);
};
