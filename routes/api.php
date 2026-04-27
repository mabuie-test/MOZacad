<?php

declare(strict_types=1);

use App\Controllers\PaymentController;
use App\Controllers\AdminApiController;
use App\Controllers\AdminCatalogController;
use App\Controllers\AdminCommercialController;
use App\Controllers\AdminGovernanceController;
use App\Controllers\AdminHumanReviewController;
use App\Controllers\AdminPaymentController;
use App\Controllers\AdminPricingController;
use App\Helpers\Router;
use App\Services\HttpRoutePolicyService;

return static function (Router $router): void {
    $policy = new HttpRoutePolicyService();
    $api = fn (callable $next) => $policy->enforceFirstPartyApi($next);
    $auth = fn (callable $next) => $policy->enforceAuthenticated($next);
    $admin = fn (callable $next) => $policy->enforceAdmin($next);

    $router->post('/api/payments/mpesa/initiate', [PaymentController::class, 'initiateMpesa'], [$api]);
    $router->get('/api/payments/{id}/status', [PaymentController::class, 'status'], [$api]);

    $router->get('/api/admin/{section}', [AdminApiController::class, 'section'], [$api, $auth, $admin]);

    $router->group('/api/admin', [$api, $auth, $admin], static function (Router $router): void {
        $router->post('/institutions', [AdminCatalogController::class, 'createInstitution']);
        $router->post('/institutions/{id}', [AdminCatalogController::class, 'updateInstitution']);
        $router->post('/courses', [AdminCatalogController::class, 'createCourse']);
        $router->post('/courses/{id}', [AdminCatalogController::class, 'updateCourse']);
        $router->post('/disciplines', [AdminCatalogController::class, 'createDiscipline']);
        $router->post('/disciplines/{id}', [AdminCatalogController::class, 'updateDiscipline']);
        $router->post('/work-types', [AdminCatalogController::class, 'createWorkType']);
        $router->post('/work-types/{id}', [AdminCatalogController::class, 'updateWorkType']);
        $router->post('/institution-rules', [AdminGovernanceController::class, 'saveInstitutionRule']);
        $router->post('/institution-work-type-rules', [AdminGovernanceController::class, 'saveInstitutionWorkTypeRule']);
        $router->post('/templates/norms', [AdminGovernanceController::class, 'publishNorm']);
        $router->post('/templates/work-type', [AdminGovernanceController::class, 'publishWorkTypeTemplate']);
        $router->post('/templates/artifacts/{artifactId}/activate', [AdminGovernanceController::class, 'activateTemplateArtifact']);
        $router->post('/discounts', [AdminCommercialController::class, 'createDiscount']);
        $router->post('/discounts/{id}', [AdminCommercialController::class, 'updateDiscount']);
        $router->post('/coupons', [AdminCommercialController::class, 'createCoupon']);
        $router->post('/coupons/{id}', [AdminCommercialController::class, 'updateCoupon']);
        $router->post('/coupons/{id}/toggle', [AdminCommercialController::class, 'toggleCoupon']);
        $router->post('/human-review/{queueId}/assign', [AdminHumanReviewController::class, 'assignHumanReview']);
        $router->post('/human-review/{queueId}/decision', [AdminHumanReviewController::class, 'decideHumanReview']);
        $router->post('/payments/{id}/confirm-manual', [AdminPaymentController::class, 'confirmManual']);
        $router->post('/pricing/rules', [AdminPricingController::class, 'upsertPricingRule']);
        $router->post('/pricing/extras', [AdminPricingController::class, 'upsertPricingExtra']);
    });
};
