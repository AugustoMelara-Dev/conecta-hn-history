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

    'booking' => [
        /*
        |----------------------------------------------------------------------
        | Public Booking Abuse Guard
        |----------------------------------------------------------------------
        |
        | Maximum active (pending/confirmed) bookings allowed per contact
        | (email or phone) for the same tenant on the same day.
        |
        */
        'max_pending_per_contact_per_day' => (int) env('CONECTA_BOOKING_MAX_PENDING_PER_CONTACT_PER_DAY', 3),
    ],

    'reports' => [
        'pdf' => [
            'max_rows' => [
                'sales' => (int) env('CONECTA_REPORTS_PDF_MAX_SALES_ROWS', 500),
                'inventory' => (int) env('CONECTA_REPORTS_PDF_MAX_INVENTORY_ROWS', 500),
                'kitchen' => (int) env('CONECTA_REPORTS_PDF_MAX_KITCHEN_ROWS', 300),
            ],
        ],
    ],

];
