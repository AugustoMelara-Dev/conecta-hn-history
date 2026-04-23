<?php

declare(strict_types=1);

return [
    'guide' => [
        /*
        |--------------------------------------------------------------------------
        | Hybrid rerank switch (RAG-like v1)
        |--------------------------------------------------------------------------
        |
        | Retrieval stays SQL-canonical for deterministic tenancy/publication
        | filtering. When enabled, we only rerank SQL recommendations by
        | query relevance. If disabled, responses remain SQL-only.
        |
        */
        'hybrid_rerank_enabled' => (bool) env('DIRECTORY_GUIDE_HYBRID_RERANK_ENABLED', false),
        'embedding_model' => env('DIRECTORY_GUIDE_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'openai_timeout_seconds' => (int) env('DIRECTORY_GUIDE_OPENAI_TIMEOUT_SECONDS', 8),
        'openai_max_retries' => (int) env('DIRECTORY_GUIDE_OPENAI_MAX_RETRIES', 1),
        'embedding_cache_ttl_seconds' => (int) env('DIRECTORY_GUIDE_EMBEDDING_CACHE_TTL_SECONDS', 300),
        'embedding_stale_minutes' => (int) env('DIRECTORY_GUIDE_EMBEDDING_STALE_MINUTES', 1440),
        'circuit_breaker' => [
            'failure_threshold' => (int) env('DIRECTORY_GUIDE_CIRCUIT_FAILURE_THRESHOLD', 3),
            'cooldown_seconds' => (int) env('DIRECTORY_GUIDE_CIRCUIT_COOLDOWN_SECONDS', 60),
        ],
        'weights' => [
            'vector_similarity' => (float) env('DIRECTORY_GUIDE_WEIGHT_VECTOR', 0.60),
            'lexical_match' => (float) env('DIRECTORY_GUIDE_WEIGHT_LEXICAL', 0.25),
            'price_fit' => (float) env('DIRECTORY_GUIDE_WEIGHT_PRICE', 0.15),
        ],
        /*
        |----------------------------------------------------------------------
        | Cost guardrail for hybrid rerank
        |----------------------------------------------------------------------
        |
        | Max number of SQL candidates that can be sent to embeddings rerank.
        | This keeps predictable token/cost usage during trial budgets.
        |
        */
        'max_rerank_candidates' => (int) env('DIRECTORY_GUIDE_MAX_RERANK_CANDIDATES', 10),
        'daily_quota' => [
            'limit' => (int) env('DIRECTORY_GUIDE_DAILY_LIMIT', 5),
        ],
        'alerts' => [
            // % of requests in window with degraded_reason=guide_runtime_unavailable
            'guide_runtime_unavailable_rate' => (float) env('DIRECTORY_GUIDE_ALERT_RUNTIME_UNAVAILABLE_RATE', 0.05),
            // absolute count in window with degraded_reason=openai_auth_failed
            'openai_auth_failed_count' => (int) env('DIRECTORY_GUIDE_ALERT_OPENAI_AUTH_FAILED_COUNT', 1),
            // % of requests in window with degraded_reason=openai_timeout
            'openai_timeout_rate' => (float) env('DIRECTORY_GUIDE_ALERT_OPENAI_TIMEOUT_RATE', 0.25),
            // % of requests in window with degraded_reason=qdrant_unavailable
            'qdrant_unavailable_rate' => (float) env('DIRECTORY_GUIDE_ALERT_QDRANT_UNAVAILABLE_RATE', 0.30),
            // % of requests in window returning HTTP 429
            'quota_exceeded_rate' => (float) env('DIRECTORY_GUIDE_ALERT_QUOTA_EXCEEDED_RATE', 0.40),
        ],
        'pgvector' => [
            'enabled' => (bool) env('PGVECTOR_ENABLED', false),
            'table' => env('PGVECTOR_TABLE', 'directory_product_embeddings'),
            'dimension' => (int) env('PGVECTOR_DIMENSION', 1536),
        ],
        'qdrant' => [
            'enabled' => (bool) env('DIRECTORY_GUIDE_QDRANT_ENABLED', false),
            'url' => env('DIRECTORY_GUIDE_QDRANT_URL', ''),
            'api_key' => env('DIRECTORY_GUIDE_QDRANT_API_KEY', ''),
            'collection' => env('DIRECTORY_GUIDE_QDRANT_COLLECTION', 'directory_products'),
            'timeout_seconds' => (int) env('DIRECTORY_GUIDE_QDRANT_TIMEOUT_SECONDS', 6),
            'max_candidates' => (int) env('DIRECTORY_GUIDE_QDRANT_MAX_CANDIDATES', 24),
        ],
    ],
];
