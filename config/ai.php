<?php

declare(strict_types=1);

return [
    'registry' => [
        'bootstrap_enabled' => (bool) env('AI_REGISTRY_BOOTSTRAP_ENABLED', false),
        'openai_secret_reference' => (string) env('AI_OPENAI_SECRET_REFERENCE', 'openai.primary'),
    ],
    'private' => [
        'enabled' => filter_var(
            env('AI_PRIVATE_ENABLED', env('TENANT_PRIVATE_RAG_LLM_ENABLED', false)),
            FILTER_VALIDATE_BOOL
        ),
        'tenant_allowlist' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('AI_PRIVATE_TENANT_ALLOWLIST', env('TENANT_PRIVATE_RAG_TENANT_ALLOWLIST', ''))),
        ))),
    ],
];
