<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Helpers\Database;
use App\Services\SchemaConvergenceService;

$mode = 'fresh';
$withSeed = false;
$withVerify = true;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--upgrade') {
        $mode = 'upgrade';
    }
    if ($arg === '--fresh') {
        $mode = 'fresh';
    }
    if ($arg === '--seed') {
        $withSeed = true;
    }
    if ($arg === '--no-verify') {
        $withVerify = false;
    }
}

$db = Database::connect();

$runSqlFile = static function (string $filePath) use ($db): void {
    $sql = file_get_contents($filePath);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Não foi possível ler SQL: ' . $filePath);
    }

    $db->exec($sql);
    echo "Executado: {$filePath}\n";
};

if ($mode === 'fresh') {
    $runSqlFile(__DIR__ . '/schema/base_schema.sql');
} else {
    $migrationFiles = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($migrationFiles, SORT_STRING);

    foreach ($migrationFiles as $file) {
        $runSqlFile($file);
    }
}

if ($withSeed) {
    $seedFiles = glob(__DIR__ . '/seeders/*.sql') ?: [];
    sort($seedFiles, SORT_STRING);

    foreach ($seedFiles as $file) {
        $runSqlFile($file);
    }
}


if ($withVerify) {
    $report = (new SchemaConvergenceService())->enforce($db, true);

    foreach ($report['issues'] as $issue) {
        echo "[schema-check] {$issue}\n";
    }

    if ($report['issues'] === []) {
        echo "[schema-check] convergência confirmada entre fresh/upgrade.\n";
    } else {
        throw new RuntimeException('Falha na verificação de convergência de schema.');
    }
}
