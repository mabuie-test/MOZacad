<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Helpers\Database;

$migrations = [
    __DIR__ . '/../database/migrations/003_job_coupon_hardening.sql',
    __DIR__ . '/../database/migrations/004_final_hardening_closure.sql',
];

$db = Database::connect();

foreach ($migrations as $migration) {
    if (!is_file($migration)) {
        fwrite(STDERR, "Migration não encontrada: {$migration}\n");
        exit(1);
    }

    $sql = file_get_contents($migration);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Migration vazia ou ilegível: {$migration}\n");
        exit(1);
    }

    $db->exec($sql);
    echo 'Migration aplicada: ' . basename($migration) . "\n";
}
