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
        'pgvector' => [
            'enabled' => (bool) env('PGVECTOR_ENABLED', false),
            'table' => env('PGVECTOR_TABLE', 'directory_product_embeddings'),
            'dimension' => (int) env('PGVECTOR_DIMENSION', 1536),
        ],
    ],
];
