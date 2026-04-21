<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\OrderController;
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
    $router->post('/orders', [OrderController::class, 'store']);
    $router->get('/orders/{id}', [OrderController::class, 'show']);
    $router->get('/orders/{id}/pay', [OrderController::class, 'pay']);
    $router->post('/orders/{id}/pay', [OrderController::class, 'pay']);
    $router->post('/orders/{id}/revision-request', [OrderController::class, 'requestRevision']);
    $router->get('/invoices', [BillingController::class, 'invoices']);
    $router->get('/downloads', [BillingController::class, 'downloads']);

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
    $router->post('/admin/discounts', [AdminController::class, 'createDiscount']);
    $router->post('/admin/discounts/{id}', [AdminController::class, 'updateDiscount']);
    $router->get('/admin/human-review', [AdminController::class, 'humanReviewQueue']);
};
