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

$ensureMigrationsTable = static function () use ($db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

if ($mode === 'fresh') {
    $runSqlFile(__DIR__ . '/schema/base_schema.sql');
    $ensureMigrationsTable();
} else {
    $ensureMigrationsTable();

    $migrationFiles = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($migrationFiles, SORT_STRING);

    $appliedStmt = $db->query('SELECT migration_name FROM schema_migrations');
    $appliedRows = $appliedStmt ? $appliedStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $applied = array_fill_keys(array_map('strval', $appliedRows ?: []), true);

    if ($applied === [] && $migrationFiles !== []) {
        $rolesExists = (bool) $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'roles'")->fetchColumn();
        if ($rolesExists) {
            $insertBaseline = $db->prepare('INSERT IGNORE INTO schema_migrations (migration_name, applied_at) VALUES (:name, NOW())');
            foreach ($migrationFiles as $file) {
                $name = basename($file);
                $insertBaseline->execute(['name' => $name]);
                $applied[$name] = true;
            }
            echo "Baseline aplicado: schema existente detectado, migrations marcadas como já aplicadas.\n";
        }
    }

    foreach ($migrationFiles as $file) {
        $name = basename($file);
        if (isset($applied[$name])) {
            echo "Ignorado (já aplicado): {$name}\n";
            continue;
        }

        if (!$db->inTransaction()) {
            $db->beginTransaction();
        }
        try {
            $runSqlFile($file);
            $stmt = $db->prepare('INSERT IGNORE INTO schema_migrations (migration_name, applied_at) VALUES (:name, NOW())');
            $stmt->execute(['name' => $name]);
            if ($db->inTransaction()) {
                $db->commit();
            }
            $applied[$name] = true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
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
