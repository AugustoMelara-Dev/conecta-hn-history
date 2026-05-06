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

    ],

];
