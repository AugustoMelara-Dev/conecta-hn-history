<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle application features without code changes. These flags replace
    | runtime schema checks (Schema::hasTable) for production performance.
    |
    */

    'features' => [

        /**
         * Enable coupon per-user tracking via the coupon_user pivot table.
         *
         * Set to false if the coupon_user migration has not been run
         * or per-user coupon limits are not needed.
         */
        'coupon_user_tracking' => (bool) env('CONECTA_COUPON_USER_TRACKING', true),
        /*
         * Private tenant AI stays deny-by-default. A tenant must be listed here
         * before the deterministic v0 agent can answer from private data.
         */
        'tenant_private_agent_tenants' => array_filter(array_map(
            'trim',
            explode(',', (string) env('TENANT_PRIVATE_AGENT_TENANTS', ''))
        )),

        /*
         * Reserved for the future LLM/RAG provider. The v0 agent remains
         * deterministic and returns honest fallbacks while this is false.
         */
        'tenant_private_rag_llm_enabled' => (bool) env('TENANT_PRIVATE_RAG_LLM_ENABLED', false),

    ],

    /*
     * Optional B2C app host. Locally this lets app.localhost render the same
     * directory experience as /directorio without replacing the path fallback.
     */
    'directory_app_host' => env('DIRECTORY_APP_HOST', 'app.localhost'),

    'uiux' => [
        'disable_filament_database_notifications' => (bool) env('UIUX_DISABLE_FILAMENT_DATABASE_NOTIFICATIONS', true),
    ],

];
