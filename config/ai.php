<?php

declare(strict_types=1);

return [
    'registry' => [
        'bootstrap_enabled' => (bool) env('AI_REGISTRY_BOOTSTRAP_ENABLED', false),
        'openai_secret_reference' => (string) env('AI_OPENAI_SECRET_REFERENCE', 'openai.primary'),
    ],
    'enabled' => (bool) env('AI_ENABLED', false),

    'allowed_environments' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('AI_ALLOWED_ENVIRONMENTS', '')),
    ))),

    'commercial_credits' => [
        // Versioned commercial unit; provider prices stay internal and may change independently.
        'pricing_version' => env('AI_CREDIT_PRICING_VERSION', 'credits-v1'),
        'credits_per_provider_usd' => (float) env('AI_CREDITS_PER_PROVIDER_USD', 20.0),
        'minimum_charge' => (float) env('AI_CREDIT_MINIMUM_CHARGE', 0.01),
    ],

    'providers' => [
        'disabled' => ['enabled' => false],
        'openai' => [
            'enabled' => (bool) env('AI_PROVIDER_OPENAI_ENABLED', false),
            'secret_reference' => (string) env('AI_OPENAI_SECRET_REFERENCE', 'openai.primary'),
            'models' => [
                'gpt-4o-mini' => [
                    'input_usd_per_million' => (float) env('OPENAI_GPT4O_MINI_INPUT_USD_PER_MILLION', 0.15),
                    'output_usd_per_million' => (float) env('OPENAI_GPT4O_MINI_OUTPUT_USD_PER_MILLION', 0.60),
                ],
            ],
        ],
    ],

    'public' => [
        // Dedicated platform tenant whose credits fund anonymous discovery.
        'billing_tenant_id' => env('AI_PUBLIC_BILLING_TENANT_ID'),
    ],

    'private' => [
        'enabled' => filter_var(
            env('AI_PRIVATE_ENABLED', env('TENANT_PRIVATE_RAG_LLM_ENABLED', false)),
            FILTER_VALIDATE_BOOL,
        ),
        'tenant_allowlist' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('AI_PRIVATE_TENANT_ALLOWLIST', env('TENANT_PRIVATE_RAG_TENANT_ALLOWLIST', ''))),
        ))),
    ],

    'cutover' => [
        'public_percent' => (int) env('AI_CANONICAL_PUBLIC_PERCENT', 0),
        'private_percent' => (int) env('AI_CANONICAL_PRIVATE_PERCENT', 0),
        'cohort_salt' => (string) env('AI_CANARY_COHORT_SALT', 'dovra-ai-foundation'),
    ],

    'tool_confirmations' => [
        'enabled' => filter_var(env('AI_TOOL_CONFIRMATIONS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'ttl_seconds' => (int) env('AI_TOOL_CONFIRMATION_TTL_SECONDS', 600),
        'requests_per_minute' => (int) env('AI_TOOL_CONFIRMATION_REQUESTS_PER_MINUTE', 10),
    ],

    'capabilities' => [
        'public_discovery' => [
            'enabled' => (bool) env('AI_PUBLIC_DISCOVERY_ENABLED', false),
            'audience' => 'public',
            'billing_mode' => 'platform',
            'provider' => (string) env('AI_PUBLIC_DISCOVERY_PROVIDER', 'openai'),
            'models' => [(string) env('AI_PUBLIC_DISCOVERY_MODEL', 'gpt-4o-mini')],
            'requires_tenant' => false,
            // Worst-case authorized spend per request; must remain positive.
            'max_cost_usd' => (float) env('AI_PUBLIC_DISCOVERY_MAX_COST_USD', env('AI_PUBLIC_DISCOVERY_ESTIMATED_COST_USD', 0.02)),
            'requests_per_minute' => (int) env('AI_PUBLIC_DISCOVERY_REQUESTS_PER_MINUTE', 20),
            'tools' => ['public_catalog.search'],
        ],
        'private_tenant_agent' => [
            'enabled' => (bool) env('AI_PRIVATE_TENANT_AGENT_ENABLED', false),
            'audience' => 'private',
            'billing_mode' => 'tenant',
            'provider' => (string) env('AI_PRIVATE_TENANT_AGENT_PROVIDER', 'openai'),
            'models' => [(string) env('AI_PRIVATE_TENANT_AGENT_MODEL', 'gpt-4o-mini')],
            'requires_tenant' => true,
            'max_cost_usd' => (float) env('AI_PRIVATE_TENANT_AGENT_MAX_COST_USD', env('AI_PRIVATE_TENANT_AGENT_ESTIMATED_COST_USD', 0.05)),
            'requests_per_minute' => (int) env('AI_PRIVATE_TENANT_AGENT_REQUESTS_PER_MINUTE', 30),
            // Direct LLM execution stays read-only. Mutations/economic actions
            // use the separate actor-bound confirmation endpoints.
            'tools' => ['tenant_catalog.read', 'tenant_availability.read'],
        ],
        'catalog_vision' => [
            'enabled' => (bool) env('AI_CATALOG_VISION_ENABLED', false),
            'audience' => 'internal',
            'billing_mode' => 'tenant',
            'provider' => (string) env('AI_CATALOG_VISION_PROVIDER', 'openai'),
            'models' => [(string) env('AI_CATALOG_VISION_MODEL', 'gpt-4o-mini')],
            'requires_tenant' => true,
            'max_cost_usd' => (float) env('AI_CATALOG_VISION_MAX_COST_USD', env('AI_CATALOG_VISION_ESTIMATED_COST_USD', 0.10)),
            'requests_per_minute' => (int) env('AI_CATALOG_VISION_REQUESTS_PER_MINUTE', 10),
            'tools' => [],
        ],
        'assistant_execution' => [
            'enabled' => (bool) env('AI_ASSISTANTS_ENABLED', false),
            'audience' => 'internal',
            'billing_mode' => 'tenant',
            'provider' => (string) env('AI_ASSISTANTS_PROVIDER', 'openai'),
            'models' => [(string) env('AI_ASSISTANTS_MODEL', 'gpt-4o-mini')],
            'requires_tenant' => true,
            'max_cost_usd' => (float) env('AI_ASSISTANTS_MAX_COST_USD', 0.10),
            'requests_per_minute' => (int) env('AI_ASSISTANTS_REQUESTS_PER_MINUTE', 20),
            'tools' => [],
        ],
    ],

    'schemas' => [
        'assistant.structured_result.v1' => [
            'capability' => 'assistant_execution',
            'rules' => [
                'summary' => ['required', 'string', 'max:4000'],
                'fields' => ['required', 'array', 'max:50'],
                'fields.*' => ['required', 'array:key,value'],
                'fields.*.key' => ['required', 'string', 'max:100'],
                'fields.*.value' => ['nullable', 'string', 'max:4000'],
                'confidence' => ['required', 'numeric', 'between:0,1'],
            ],
            'json_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary', 'fields', 'confidence'],
                'properties' => [
                    'summary' => ['type' => 'string', 'pattern' => '.*\\S.*'],
                    'fields' => [
                        'type' => 'array',
                        'maxItems' => 50,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['key', 'value'],
                            'properties' => [
                                'key' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,99}$'],
                                'value' => ['type' => ['string', 'null']],
                            ],
                        ],
                    ],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
            ],
        ],
        'public_discovery.answer.v1' => [
            'capability' => 'public_discovery',
            'rules' => [
                'answer' => ['required', 'string', 'max:2000'],
                'evidence' => ['required', 'array', 'max:20'],
                'evidence.*' => ['required', 'array:type,id,reason'],
                'evidence.*.type' => ['required', 'string', 'in:product,service,business'],
                'evidence.*.id' => ['required', 'string', 'max:64'],
                'evidence.*.reason' => ['required', 'string', 'max:500'],
            ],
            'json_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['answer', 'evidence'],
                'properties' => [
                    'answer' => ['type' => 'string', 'pattern' => '.*\S.*'],
                    'evidence' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 20,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['type', 'id', 'reason'],
                            'properties' => [
                                'type' => ['type' => 'string', 'enum' => ['product', 'service', 'business']],
                                'id' => ['type' => 'string', 'pattern' => '.*\S.*'],
                                'reason' => ['type' => 'string', 'pattern' => '.*\S.*'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'private_agent.evidence_selection.v1' => [
            'capability' => 'private_tenant_agent',
            'rules' => [
                'evidence' => ['required', 'array', 'min:1', 'max:20'],
                'evidence.*' => ['required', 'array:type,id'],
                'evidence.*.type' => ['required', 'string', 'in:product,service'],
                'evidence.*.id' => ['required', 'string', 'max:64'],
            ],
            'json_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['evidence'],
                'properties' => [
                    'evidence' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 20,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['type', 'id'],
                            'properties' => [
                                'type' => ['type' => 'string', 'enum' => ['product', 'service']],
                                'id' => ['type' => 'string', 'pattern' => '.*\S.*'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'private_agent.answer.v1' => [
            'capability' => 'private_tenant_agent',
            'rules' => [
                'answer' => ['required', 'string', 'max:2000'],
            ],
            'json_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['answer'],
                'properties' => [
                    'answer' => ['type' => 'string', 'pattern' => '.*\S.*'],
                ],
            ],
        ],
    ],

    'tools' => [
        'public_catalog.search' => ['risk' => 'read'],
        'tenant_catalog.read' => ['risk' => 'read'],
        'tenant_availability.read' => ['risk' => 'read'],
        'tenant_sales.read' => ['risk' => 'read'],
        'tenant_appointments.read' => ['risk' => 'read'],
        'tenant_lead.prepare' => [
            'risk' => 'mutation',
            'confirmation_required' => true,
        ],
        'commerce.order.create' => [
            'risk' => 'economic',
            'confirmation_required' => true,
        ],
    ],

    'limits' => [
        'timeout_milliseconds' => (int) env('AI_TIMEOUT_MILLISECONDS', 15000),
        'max_user_input_characters' => (int) env('AI_MAX_USER_INPUT_CHARACTERS', 2000),
        'max_system_prompt_characters' => (int) env('AI_MAX_SYSTEM_PROMPT_CHARACTERS', 12000),
        'max_result_bytes' => (int) env('AI_MAX_RESULT_BYTES', 65536),
        'max_result_depth' => (int) env('AI_MAX_RESULT_DEPTH', 8),
    ],

    'execution_retention' => [
        'days' => (int) env('AI_EXECUTION_RETENTION_DAYS', 30),
        'encryption_key_version' => (string) env('AI_ENCRYPTION_KEY_VERSION', 'v1'),
    ],

    'retention' => [
        'conversation_days' => (int) env('AI_CONVERSATION_RETENTION_DAYS', 30),
    ],

    'knowledge' => [
        'enabled' => (bool) env('AI_KNOWLEDGE_ENABLED', false),
        'provider' => (string) env('AI_EMBEDDING_PROVIDER', 'openai'),
        'model' => (string) env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'embedding_dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
        'minimum_similarity' => (float) env('AI_KNOWLEDGE_MINIMUM_SIMILARITY', 0.55),
        'max_results' => (int) env('AI_KNOWLEDGE_MAX_RESULTS', 20),
        'metering_enabled' => (bool) env('AI_EMBEDDING_METERING_ENABLED', false),
        'max_cost_usd' => (float) env('AI_EMBEDDING_MAX_COST_USD', 0.001),
        'input_usd_per_million' => (float) env('AI_EMBEDDING_INPUT_USD_PER_MILLION', 0.02),
    ],

    'circuit' => [
        'failure_threshold' => (int) env('AI_CIRCUIT_FAILURE_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('AI_CIRCUIT_COOLDOWN_SECONDS', 60),
    ],
];
