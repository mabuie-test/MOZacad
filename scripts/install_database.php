<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Helpers\Database;

$withSeed = in_array('--seed', $argv, true);
$schemaPath = __DIR__ . '/../database/schema/base.sql';

if (!is_file($schemaPath)) {
    fwrite(STDERR, "Schema base não encontrado: {$schemaPath}\n");
    exit(1);
}

$sql = file_get_contents($schemaPath);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Schema base vazio ou ilegível: {$schemaPath}\n");
    exit(1);
}

Database::connect()->exec($sql);
echo "Schema base aplicado com sucesso: database/schema/base.sql\n";

if ($withSeed) {
    require __DIR__ . '/../database/seeders/SeederRunner.php';
}
