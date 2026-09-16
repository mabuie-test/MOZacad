<?php

declare(strict_types=1);

use App\Helpers\Database;
use App\Helpers\Env;

require_once __DIR__ . '/../vendor/autoload.php';
Env::boot(__DIR__ . '/../.env');
Database::connect();
