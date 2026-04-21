<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap/app.php';

use App\Helpers\Database;

foreach (glob(__DIR__ . '/*.sql') as $file) {
    $sql = file_get_contents($file);
    if ($sql) {
        Database::connect()->exec($sql);
        echo "Seed executado: {$file}\n";
    }
}
