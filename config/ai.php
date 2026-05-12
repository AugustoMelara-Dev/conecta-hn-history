<?php

declare(strict_types=1);

return [
    'private' => [
        'enabled' => env('AI_PRIVATE_ENABLED', env('TENANT_PRIVATE_RAG_LLM_ENABLED', false)),
        'tenant_allowlist' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('AI_PRIVATE_TENANT_ALLOWLIST', env('TENANT_PRIVATE_RAG_TENANT_ALLOWLIST', ''))),
        ))),
    ],
];
