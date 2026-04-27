<?php

declare(strict_types=1);

return [
    'embedding_model' => env('AI_RAG_EMBEDDING_MODEL', env('DIRECTORY_GUIDE_EMBEDDING_MODEL', 'text-embedding-3-small')),
    'embedding_dimension' => (int) env('AI_RAG_EMBEDDING_DIMENSION', 1536),
    'embedding_cache_ttl_seconds' => (int) env('AI_RAG_EMBEDDING_CACHE_TTL_SECONDS', 900),

    'scopes' => [
        'directory_public' => [
            'collection' => env('DIRECTORY_QDRANT_COLLECTION', 'directory_products'),
            'sql_table' => 'directory_product_embeddings',
            'visibility' => 'published',
            'allows_private_sources' => false,
        ],
        'tenant_private' => [
            'collection' => env('TENANT_PRIVATE_QDRANT_COLLECTION', 'tenant_private_documents'),
            'sql_table' => 'tenant_knowledge_embeddings',
            'visibility' => 'private',
            'allows_private_sources' => true,
            'required_payload' => [
                'tenant_id',
                'rag_scope',
                'source_type',
                'source_id',
                'visibility',
                'redaction_version',
            ],
        ],
    ],

    'redaction' => [
        'version' => 'private-redaction-v1',
        'deny_fields' => [
            'email',
            'phone',
            'address',
            'access_token',
            'refresh_token',
            'password',
        ],
    ],

    'tenant_private' => [
        'chat_model' => env('TENANT_PRIVATE_RAG_CHAT_MODEL', 'gpt-4o-mini'),
        'llm_enabled' => (bool) env('TENANT_PRIVATE_RAG_LLM_ENABLED', true),
        'vector_store' => [
            'driver' => env('TENANT_PRIVATE_RAG_VECTOR_STORE', 'sql'),
        ],
        'qdrant' => [
            'enabled' => (bool) env('TENANT_PRIVATE_QDRANT_ENABLED', false),
            'url' => env('TENANT_PRIVATE_QDRANT_URL', env('QDRANT_URL', '')),
            'api_key' => env('TENANT_PRIVATE_QDRANT_API_KEY', env('QDRANT_API_KEY', '')),
            'timeout_seconds' => (int) env('TENANT_PRIVATE_QDRANT_TIMEOUT_SECONDS', 6),
            'max_candidates' => (int) env('TENANT_PRIVATE_QDRANT_MAX_CANDIDATES', 24),
            'upsert_batch_size' => (int) env('TENANT_PRIVATE_QDRANT_UPSERT_BATCH_SIZE', 100),
            'wait_for_upsert' => (bool) env('TENANT_PRIVATE_QDRANT_WAIT_FOR_UPSERT', false),
        ],
        'openai_timeout_seconds' => (int) env('TENANT_PRIVATE_RAG_OPENAI_TIMEOUT_SECONDS', 12),
        'max_completion_tokens' => (int) env('TENANT_PRIVATE_RAG_MAX_COMPLETION_TOKENS', 700),
        'estimated_chat_cost_usd' => (float) env('TENANT_PRIVATE_RAG_ESTIMATED_CHAT_COST_USD', 0.002),
        'chat_input_cost_per_1m_tokens' => (float) env('TENANT_PRIVATE_RAG_CHAT_INPUT_COST_PER_1M_TOKENS', 0.15),
        'chat_output_cost_per_1m_tokens' => (float) env('TENANT_PRIVATE_RAG_CHAT_OUTPUT_COST_PER_1M_TOKENS', 0.60),
        'max_context_chunks' => (int) env('TENANT_PRIVATE_RAG_MAX_CONTEXT_CHUNKS', 8),
        'max_prompt_chars' => (int) env('TENANT_PRIVATE_RAG_MAX_PROMPT_CHARS', 12000),
        'max_sources' => (int) env('TENANT_PRIVATE_RAG_MAX_SOURCES', 8),
        'max_query_chars' => (int) env('TENANT_PRIVATE_RAG_MAX_QUERY_CHARS', 500),
        'max_document_chars' => (int) env('TENANT_PRIVATE_RAG_MAX_DOCUMENT_CHARS', 3000),
        'max_index_products' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_PRODUCTS', 500),
        'max_index_services' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_SERVICES', 300),
        'max_index_customers' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_CUSTOMERS', 300),
        'max_index_orders' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_ORDERS', 300),
        'embedding_batch_size' => (int) env('TENANT_PRIVATE_RAG_EMBEDDING_BATCH_SIZE', 64),
        'rate_limit_per_minute' => (int) env('TENANT_PRIVATE_RAG_RATE_LIMIT_PER_MINUTE', 10),
        'rate_limits' => [
            'per_user_minute' => (int) env('TENANT_PRIVATE_RAG_USER_RATE_LIMIT_PER_MINUTE', 20),
            'per_tenant_minute' => (int) env('TENANT_PRIVATE_RAG_TENANT_RATE_LIMIT_PER_MINUTE', 60),
        ],
    ],

    // Backward-compatible runtime key used by the current BusinessAi services.
    'private' => [
        'chat_model' => env('TENANT_PRIVATE_RAG_CHAT_MODEL', 'gpt-4o-mini'),
        'llm_enabled' => (bool) env('TENANT_PRIVATE_RAG_LLM_ENABLED', true),
        'vector_store' => [
            'driver' => env('TENANT_PRIVATE_RAG_VECTOR_STORE', 'sql'),
        ],
        'qdrant' => [
            'enabled' => (bool) env('TENANT_PRIVATE_QDRANT_ENABLED', false),
            'url' => env('TENANT_PRIVATE_QDRANT_URL', env('QDRANT_URL', '')),
            'api_key' => env('TENANT_PRIVATE_QDRANT_API_KEY', env('QDRANT_API_KEY', '')),
            'timeout_seconds' => (int) env('TENANT_PRIVATE_QDRANT_TIMEOUT_SECONDS', 6),
            'max_candidates' => (int) env('TENANT_PRIVATE_QDRANT_MAX_CANDIDATES', 24),
            'upsert_batch_size' => (int) env('TENANT_PRIVATE_QDRANT_UPSERT_BATCH_SIZE', 100),
            'wait_for_upsert' => (bool) env('TENANT_PRIVATE_QDRANT_WAIT_FOR_UPSERT', false),
        ],
        'openai_timeout_seconds' => (int) env('TENANT_PRIVATE_RAG_OPENAI_TIMEOUT_SECONDS', 12),
        'max_completion_tokens' => (int) env('TENANT_PRIVATE_RAG_MAX_COMPLETION_TOKENS', 700),
        'estimated_chat_cost_usd' => (float) env('TENANT_PRIVATE_RAG_ESTIMATED_CHAT_COST_USD', 0.002),
        'chat_input_cost_per_1m_tokens' => (float) env('TENANT_PRIVATE_RAG_CHAT_INPUT_COST_PER_1M_TOKENS', 0.15),
        'chat_output_cost_per_1m_tokens' => (float) env('TENANT_PRIVATE_RAG_CHAT_OUTPUT_COST_PER_1M_TOKENS', 0.60),
        'max_context_chunks' => (int) env('TENANT_PRIVATE_RAG_MAX_CONTEXT_CHUNKS', 8),
        'max_prompt_chars' => (int) env('TENANT_PRIVATE_RAG_MAX_PROMPT_CHARS', 12000),
        'max_sources' => (int) env('TENANT_PRIVATE_RAG_MAX_SOURCES', 8),
        'max_query_chars' => (int) env('TENANT_PRIVATE_RAG_MAX_QUERY_CHARS', 500),
        'max_document_chars' => (int) env('TENANT_PRIVATE_RAG_MAX_DOCUMENT_CHARS', 3000),
        'max_index_products' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_PRODUCTS', 500),
        'max_index_services' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_SERVICES', 300),
        'max_index_customers' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_CUSTOMERS', 300),
        'max_index_orders' => (int) env('TENANT_PRIVATE_RAG_MAX_INDEX_ORDERS', 300),
        'embedding_batch_size' => (int) env('TENANT_PRIVATE_RAG_EMBEDDING_BATCH_SIZE', 64),
        'rate_limit_per_minute' => (int) env('TENANT_PRIVATE_RAG_RATE_LIMIT_PER_MINUTE', 10),
        'rate_limits' => [
            'per_user_minute' => (int) env('TENANT_PRIVATE_RAG_USER_RATE_LIMIT_PER_MINUTE', 20),
            'per_tenant_minute' => (int) env('TENANT_PRIVATE_RAG_TENANT_RATE_LIMIT_PER_MINUTE', 60),
        ],
    ],
];
