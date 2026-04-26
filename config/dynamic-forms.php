<?php

declare(strict_types=1);

return [
    'field_types' => [
        'text',
        'textarea',
        'number',
        'money',
        'date',
        'datetime',
        'select',
        'multiselect',
        'boolean',
        'file',
        'relation',
        'signature',
        'richtext',
    ],

    'storage_modes' => [
        'json',
        'indexed',
        'core',
    ],

    'lifecycle_statuses' => [
        'draft',
        'review',
        'published',
        'archived',
    ],

    'allowed_hook_actions' => [
        'set_default',
        'normalize_string',
        'append_timestamp',
    ],

    'allowed_template_blocks' => [
        'header',
        'paragraph',
        'kv',
        'table',
        'divider',
        'footer',
    ],

    'contract_defaults' => [
        'schema' => [
            'semver' => '1.0.0',
            'compatibility_mode' => 'strict',
        ],
        'template' => [
            'semver' => '1.0.0',
            'placeholder_schema_version' => '1.0.0',
        ],
    ],

    'compiled_cache' => [
        'ttl_seconds' => (int) env('DYNAMIC_FORMS_COMPILED_CACHE_TTL', 900),
    ],

    'default_record_status' => 'active',
];
