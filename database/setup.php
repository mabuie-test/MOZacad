<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Helpers\Database;

$mode = 'fresh';
$withSeed = false;

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
