<?php

return [
    'currency' => $_ENV['PRICING_CURRENCY'] ?? 'MZN',
    'db_overrides' => filter_var($_ENV['PRICING_ENABLE_DB_OVERRIDES'] ?? true, FILTER_VALIDATE_BOOL),
];
