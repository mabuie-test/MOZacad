<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AdminCatalogController;
use App\Controllers\AdminCommercialController;
use App\Controllers\AdminGovernanceController;
use App\Controllers\AdminHumanReviewController;
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

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/about', [HomeController::class, 'about']);
    $router->get('/how-it-works', [HomeController::class, 'howItWorks']);
    $router->get('/institutions', [HomeController::class, 'institutions']);
    $router->get('/pricing', [HomeController::class, 'pricing']);
    $router->get('/faq', [HomeController::class, 'faq']);
    $router->get('/contact', [HomeController::class, 'contact']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/orders', [OrderController::class, 'index']);
    $router->get('/orders/create', [OrderController::class, 'create']);
    $router->get('/orders/meta/courses', [OrderController::class, 'metaCourses']);
    $router->get('/orders/meta/disciplines', [OrderController::class, 'metaDisciplines']);
    $router->post('/orders', [OrderController::class, 'store']);
    $router->get('/orders/{id}', [OrderController::class, 'show']);
    $router->get('/orders/{id}/pay', [OrderController::class, 'pay']);
    $router->post('/orders/{id}/pay', [OrderController::class, 'pay']);
    $router->post('/orders/{id}/revision-request', [OrderController::class, 'requestRevision']);
    $router->post('/payments/mpesa/initiate', [PaymentController::class, 'initiateMpesa']);
    $router->get('/payments/{id}/status', [PaymentController::class, 'status']);
    $router->get('/invoices', [BillingController::class, 'invoices']);
    $router->get('/downloads', [BillingController::class, 'downloads']);
    $router->get('/downloads/{documentId}', [BillingController::class, 'downloadDocument']);

    $webhookPath = (string) Env::get('DEBITO_WEBHOOK_PATH', '/webhooks/debito');
    $router->post($webhookPath, [DebitoWebhookController::class, 'handle']);

    $router->get('/admin', [AdminController::class, 'index']);
    $router->get('/admin/users', [AdminController::class, 'users']);
    $router->get('/admin/orders', [AdminController::class, 'orders']);
    $router->get('/admin/payments', [AdminController::class, 'payments']);
    $router->get('/admin/institutions', [AdminController::class, 'institutions']);
    $router->get('/admin/courses', [AdminController::class, 'courses']);
    $router->get('/admin/disciplines', [AdminController::class, 'disciplines']);
    $router->get('/admin/work-types', [AdminController::class, 'workTypes']);
    $router->get('/admin/pricing', [AdminController::class, 'pricing']);
    $router->get('/admin/discounts', [AdminController::class, 'discounts']);
    $router->get('/admin/institution-rules', [AdminController::class, 'institutionRules']);
    $router->get('/admin/templates', [AdminController::class, 'templates']);
    $router->get('/admin/coupons', [AdminController::class, 'coupons']);
    $router->post('/admin/institutions', [AdminCatalogController::class, 'createInstitution']);
    $router->post('/admin/institutions/{id}', [AdminCatalogController::class, 'updateInstitution']);
    $router->post('/admin/courses', [AdminCatalogController::class, 'createCourse']);
    $router->post('/admin/courses/{id}', [AdminCatalogController::class, 'updateCourse']);
    $router->post('/admin/disciplines', [AdminCatalogController::class, 'createDiscipline']);
    $router->post('/admin/disciplines/{id}', [AdminCatalogController::class, 'updateDiscipline']);
    $router->post('/admin/work-types', [AdminCatalogController::class, 'createWorkType']);
    $router->post('/admin/work-types/{id}', [AdminCatalogController::class, 'updateWorkType']);
    $router->post('/admin/institution-rules', [AdminGovernanceController::class, 'saveInstitutionRule']);
    $router->post('/admin/institution-work-type-rules', [AdminGovernanceController::class, 'saveInstitutionWorkTypeRule']);
    $router->post('/admin/templates/norms', [AdminGovernanceController::class, 'publishNorm']);
    $router->post('/admin/templates/work-type', [AdminGovernanceController::class, 'publishWorkTypeTemplate']);
    $router->post('/admin/discounts', [AdminCommercialController::class, 'createDiscount']);
    $router->post('/admin/discounts/{id}', [AdminCommercialController::class, 'updateDiscount']);
    $router->post('/admin/coupons', [AdminCommercialController::class, 'createCoupon']);
    $router->post('/admin/coupons/{id}', [AdminCommercialController::class, 'updateCoupon']);
    $router->post('/admin/coupons/{id}/toggle', [AdminCommercialController::class, 'toggleCoupon']);
    $router->get('/admin/human-review', [AdminController::class, 'humanReviewQueue']);
    $router->post('/admin/human-review/{queueId}/assign', [AdminHumanReviewController::class, 'assignHumanReview']);
    $router->post('/admin/human-review/{queueId}/decision', [AdminHumanReviewController::class, 'decideHumanReview']);
    $router->post('/admin/pricing/rules', [AdminPricingController::class, 'upsertPricingRule']);
    $router->post('/admin/pricing/extras', [AdminPricingController::class, 'upsertPricingExtra']);
};
