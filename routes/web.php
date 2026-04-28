<?php

declare(strict_types=1);

use App\Controllers\AdminCatalogController;
use App\Controllers\AdminCommercialController;
use App\Controllers\AdminController;
use App\Controllers\AdminGovernanceController;
use App\Controllers\AdminGovernancePageController;
use App\Controllers\AdminCommercialPageController;
use App\Controllers\AdminReviewPageController;
use App\Controllers\AdminHumanReviewController;
use App\Controllers\AdminPaymentController;
use App\Controllers\AdminPermissionController;
use App\Controllers\AdminPricingController;
use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\DashboardController;
use App\Controllers\DebitoWebhookController;
use App\Controllers\HomeController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Helpers\Env;
use App\Helpers\Router;
use App\Services\HttpRoutePolicyService;

return static function (Router $router): void {
    $policy = new HttpRoutePolicyService();
    $csrf = fn (callable $next) => $policy->enforceCsrfForMutations($next);
    $auth = fn (callable $next) => $policy->enforceAuthenticated($next);
    $admin = fn (callable $next) => $policy->enforceAdmin($next);

    $router->get('/', [HomeController::class, 'index']);
    $router->get('/about', [HomeController::class, 'about']);
    $router->get('/how-it-works', [HomeController::class, 'howItWorks']);
    $router->get('/institutions', [HomeController::class, 'institutions']);
    $router->get('/pricing', [HomeController::class, 'pricing']);
    $router->get('/faq', [HomeController::class, 'faq']);
    $router->get('/contact', [HomeController::class, 'contact']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login'], [$csrf]);
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register'], [$csrf]);
    $router->post('/logout', [AuthController::class, 'logout'], [$auth, $csrf]);

    $router->group('', [$auth], static function (Router $router) use ($csrf): void {
        $router->get('/dashboard', [DashboardController::class, 'index']);
        $router->get('/orders', [OrderController::class, 'index']);
        $router->get('/orders/create', [OrderController::class, 'create']);
        $router->get('/orders/meta/courses', [OrderController::class, 'metaCourses']);
        $router->get('/orders/meta/disciplines', [OrderController::class, 'metaDisciplines']);
        $router->post('/orders', [OrderController::class, 'store'], [$csrf]);
        $router->get('/orders/{id}', [OrderController::class, 'show']);
        $router->get('/orders/{id}/pay', [OrderController::class, 'pay']);
        $router->post('/orders/{id}/pay', [OrderController::class, 'pay'], [$csrf]);
        $router->post('/orders/{id}/revision-request', [OrderController::class, 'requestRevision'], [$csrf]);
        $router->post('/payments/mpesa/initiate', [PaymentController::class, 'initiateMpesa'], [$csrf]);
        $router->get('/payments/{id}/status', [PaymentController::class, 'status']);
        $router->get('/invoices', [BillingController::class, 'invoices']);
        $router->get('/downloads', [BillingController::class, 'downloads']);
        $router->get('/downloads/{documentId}', [BillingController::class, 'downloadDocument']);
    });

    $webhookPath = (string) Env::get('DEBITO_WEBHOOK_PATH', '/webhooks/debito');
    $router->post($webhookPath, [DebitoWebhookController::class, 'handle']);

    $router->group('/admin', [$admin], static function (Router $router) use ($csrf): void {
        $router->get('', [AdminController::class, 'index']);
        $router->get('/users', [AdminController::class, 'users']);
        $router->get('/orders', [AdminController::class, 'orders']);
        $router->get('/payments', [AdminController::class, 'payments']);
        $router->get('/institutions', [AdminController::class, 'institutions']);
        $router->get('/courses', [AdminController::class, 'courses']);
        $router->get('/disciplines', [AdminController::class, 'disciplines']);
        $router->get('/work-types', [AdminController::class, 'workTypes']);
        $router->get('/pricing', [AdminCommercialPageController::class, 'pricing']);
        $router->get('/discounts', [AdminCommercialPageController::class, 'discounts']);
        $router->get('/institution-rules', [AdminGovernancePageController::class, 'institutionRules']);
        $router->get('/templates', [AdminGovernancePageController::class, 'templates']);
        $router->get('/coupons', [AdminCommercialPageController::class, 'coupons']);
        $router->get('/human-review', [AdminReviewPageController::class, 'humanReviewQueue']);
        $router->get('/permissions', [AdminController::class, 'permissions']);

        $router->post('/institutions', [AdminCatalogController::class, 'createInstitution'], [$csrf]);
        $router->post('/institutions/{id}', [AdminCatalogController::class, 'updateInstitution'], [$csrf]);
        $router->post('/courses', [AdminCatalogController::class, 'createCourse'], [$csrf]);
        $router->post('/courses/{id}', [AdminCatalogController::class, 'updateCourse'], [$csrf]);
        $router->post('/disciplines', [AdminCatalogController::class, 'createDiscipline'], [$csrf]);
        $router->post('/disciplines/{id}', [AdminCatalogController::class, 'updateDiscipline'], [$csrf]);
        $router->post('/work-types', [AdminCatalogController::class, 'createWorkType'], [$csrf]);
        $router->post('/work-types/{id}', [AdminCatalogController::class, 'updateWorkType'], [$csrf]);
        $router->post('/institution-rules', [AdminGovernanceController::class, 'saveInstitutionRule'], [$csrf]);
        $router->post('/institution-work-type-rules', [AdminGovernanceController::class, 'saveInstitutionWorkTypeRule'], [$csrf]);
        $router->post('/templates/norms', [AdminGovernanceController::class, 'publishNorm'], [$csrf]);
        $router->post('/templates/work-type', [AdminGovernanceController::class, 'publishWorkTypeTemplate'], [$csrf]);
        $router->post('/templates/artifacts/{artifactId}/activate', [AdminGovernanceController::class, 'activateTemplateArtifact'], [$csrf]);
        $router->post('/discounts', [AdminCommercialController::class, 'createDiscount'], [$csrf]);
        $router->post('/discounts/{id}', [AdminCommercialController::class, 'updateDiscount'], [$csrf]);
        $router->post('/coupons', [AdminCommercialController::class, 'createCoupon'], [$csrf]);
        $router->post('/coupons/{id}', [AdminCommercialController::class, 'updateCoupon'], [$csrf]);
        $router->post('/coupons/{id}/toggle', [AdminCommercialController::class, 'toggleCoupon'], [$csrf]);
        $router->post('/human-review/{queueId}/assign', [AdminHumanReviewController::class, 'assignHumanReview'], [$csrf]);
        $router->post('/human-review/{queueId}/decision', [AdminHumanReviewController::class, 'decideHumanReview'], [$csrf]);
        $router->post('/payments/{id}/confirm-manual', [AdminPaymentController::class, 'confirmManual'], [$csrf]);
        $router->post('/operations/process-ai-queue', [AdminPaymentController::class, 'processAiQueueNow'], [$csrf]);
        $router->post('/pricing/rules', [AdminPricingController::class, 'upsertPricingRule'], [$csrf]);
        $router->post('/pricing/extras', [AdminPricingController::class, 'upsertPricingExtra'], [$csrf]);
        $router->post('/permissions/matrix', [AdminPermissionController::class, 'updateMatrix'], [$csrf]);
    });
};
