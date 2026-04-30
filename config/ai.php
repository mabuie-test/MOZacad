<?php

return [
    'provider' => [
        'default' => $_ENV['AI_PROVIDER'] ?? 'openai',
        'mode' => $_ENV['AI_PROVIDER_MODE'] ?? 'failover',
        'failover' => [
            'enabled' => filter_var($_ENV['AI_FAILOVER_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
            'chain' => [
                'openai' => ['openai', 'gemini'],
                'gemini' => ['gemini', 'openai'],
            ],
        ],
    ],
    'models' => [
        'openai' => [
            'default' => $_ENV['OPENAI_MODEL'] ?? 'gpt-5',
            'tasks' => [
                'content' => $_ENV['OPENAI_MODEL_CONTENT'] ?? null,
                'refinement' => $_ENV['OPENAI_MODEL_REFINEMENT'] ?? null,
                'humanizer' => $_ENV['OPENAI_MODEL_HUMANIZER'] ?? null,
                'structure' => $_ENV['OPENAI_MODEL_STRUCTURE'] ?? null,
            ],
        ],
        'gemini' => [
            'default' => $_ENV['GEMINI_MODEL'] ?? 'gemini-2.5-flash',
            'tasks' => [
                'content' => $_ENV['GEMINI_MODEL_CONTENT'] ?? null,
                'refinement' => $_ENV['GEMINI_MODEL_REFINEMENT'] ?? null,
                'humanizer' => $_ENV['GEMINI_MODEL_HUMANIZER'] ?? null,
                'structure' => $_ENV['GEMINI_MODEL_STRUCTURE'] ?? null,
            ],
        ],
    ],
    'openai' => [
        'api_key' => $_ENV['OPENAI_API_KEY'] ?? null,
        'base_url' => $_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1',
        'timeout' => (int) ($_ENV['OPENAI_TIMEOUT'] ?? 60),
        'temperature' => (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.7),
        'max_output_tokens' => (int) ($_ENV['OPENAI_MAX_OUTPUT_TOKENS'] ?? 4000),
        'enable_structured_output' => filter_var($_ENV['OPENAI_ENABLE_STRUCTURED_OUTPUT'] ?? false, FILTER_VALIDATE_BOOL),
    ],
    'gemini' => [
        'api_key' => $_ENV['GEMINI_API_KEY'] ?? null,
        'base_url' => $_ENV['GEMINI_BASE_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta',
        'timeout' => (int) ($_ENV['GEMINI_TIMEOUT'] ?? 60),
        'temperature' => (float) ($_ENV['GEMINI_TEMPERATURE'] ?? 0.7),
        'max_output_tokens' => (int) ($_ENV['GEMINI_MAX_OUTPUT_TOKENS'] ?? 4000),
    ],
    'required' => [
        'provider.default',
        'provider.mode',
        'openai.api_key',
        'gemini.api_key',
    ],
    'preflight' => [
        'stale_minutes' => (int) ($_ENV['AI_PREFLIGHT_STALE_MINUTES'] ?? 10),
    ],
];
