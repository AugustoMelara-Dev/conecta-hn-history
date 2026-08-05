<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\Capability;

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical subscription catalog
    |--------------------------------------------------------------------------
    |
    | Prices use minor HNL units. AI allowances are internal commercial
    | credits; provider tokens and raw provider prices are never customer-facing.
    |
    */
    'plans' => [
        'semilla' => [
            'id' => 'semilla',
            'name' => 'Semilla',
            'description' => 'Presencia digital y herramientas esenciales para empezar.',
            'price' => 0,
            'currency' => 'HNL',
            'features' => [
                'Micrositio y directorio publico',
                'Catalogo o servicios esenciales',
                'IA con limite mensual de prueba',
                'Sin comision sobre ventas',
            ],
            'limits' => [
                'products' => 10,
                'services' => 10,
                'locations' => 1,
                'users' => 1,
                'ai_monthly_credits' => 1.0,
                'ai_daily_credits' => 1.0,
                'photos_processed' => 20,
                'storage_mb' => 250,
                'automations' => 1,
                'history_days' => 30,
                'channels' => 1,
            ],
            'entitlements' => [
                Capability::BILLING_PORTAL->value,
                Capability::CATALOG_BASIC->value,
                Capability::CHECKOUT_BASIC->value,
                Capability::CUSTOMER_DIRECTORY->value,
                Capability::NOTIFICATIONS_BASIC->value,
            ],
            'is_active' => true,
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'PRO',
            'description' => 'Operacion, clientes, automatizacion e IA privada para crecer.',
            'price' => 49900,
            'original_price' => 79900,
            'currency' => 'HNL',
            'stripe_price_id' => env('STRIPE_PRICE_PRO'),
            'features' => [
                'Catalogo, pedidos, reservas y CRM',
                'Agente IA privado y analitica',
                'Automatizaciones y equipo',
                'Sin comision sobre ventas',
            ],
            'limits' => [
                'products' => 100,
                'services' => 100,
                'locations' => 3,
                'users' => 5,
                'ai_monthly_credits' => 15.0,
                'ai_daily_credits' => 3.0,
                'photos_processed' => 500,
                'storage_mb' => 10240,
                'automations' => 20,
                'history_days' => 365,
                'channels' => 3,
            ],
            'entitlements' => array_map(
                static fn (Capability $capability): string => $capability->value,
                Capability::cases(),
            ),
            'is_featured' => true,
            'is_active' => true,
        ],
        'enterprise' => [
            'id' => 'enterprise',
            'name' => 'Empresa',
            'description' => 'Limites, ubicaciones, gobierno y soporte acordados por contrato.',
            'price' => 0,
            'currency' => 'HNL',
            'features' => [
                'Configuracion contractual',
                'Auditoria y soporte prioritario',
                'Sin comision sobre ventas',
            ],
            'limits' => [
                'products' => 1000,
                'services' => 1000,
                'locations' => 25,
                'users' => 100,
                'ai_monthly_credits' => 100.0,
                'ai_daily_credits' => 20.0,
                'photos_processed' => 5000,
                'storage_mb' => 102400,
                'automations' => 250,
                'history_days' => 1095,
                'channels' => 15,
            ],
            'entitlements' => array_map(
                static fn (Capability $capability): string => $capability->value,
                Capability::cases(),
            ),
            // Never expose a zero-price enterprise checkout accidentally.
            'is_active' => false,
        ],
    ],

    // Temporary read compatibility for code deployed before the Semilla rename.
    'legacy_plan_aliases' => [
        'free' => 'semilla',
        'seed' => 'semilla',
    ],

    'usage_alert_thresholds' => [70, 90, 100],

    'payment_methods' => [
        [
            'id' => 'bank_transfer',
            'type' => 'offline',
            'name' => 'Transferencia bancaria',
            'description' => 'BAC, Banco Atlantida, Ficohsa y otros bancos habilitados.',
            'instructions_key' => 'payment.bank_instructions',
        ],
    ],
];
