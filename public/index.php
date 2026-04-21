<?php

declare(strict_types=1);

use App\Helpers\Env;
use App\Helpers\Router;

require_once __DIR__ . '/../vendor/autoload.php';

Env::boot(__DIR__ . '/../.env');

$router = new Router();
(require __DIR__ . '/../routes/web.php')($router);
(require __DIR__ . '/../routes/api.php')($router);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
