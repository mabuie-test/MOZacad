<?php

declare(strict_types=1);

$commands = [
    'php database/setup.php --upgrade',
    'php scripts/validate_runtime.php',
];

foreach ($commands as $command) {
    passthru($command, $status);
    if ($status !== 0) {
        fwrite(STDERR, sprintf("[deploy] Falhou: %s\n", $command));
        exit($status);
    }
}

echo "[deploy] Upgrade + validações operacionais concluídos com sucesso.\n";
