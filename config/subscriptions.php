<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Defines the available tiers for the SaaS platform.
    | Prices are in minor units (e.g., centavos/cents).
    |
    */
    'plans' => [
        'free' => [
            'id' => 'free',
            'name' => 'Plan Inicial',
            'description' => 'Ideal para probar la plataforma',
            'price' => 0,
            'features' => [
                '1 Producto visible',
                'Perfil básico',
                'Horarios de atención',
            ],
            'is_active' => true,
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Plan PRO',
            'description' => 'Para negocios en crecimiento',
            'price' => 49900, // L. 499.00
            'original_price' => 79900, // Strike-through price
            'currency' => 'HNL',
            'features' => [
                'Productos ilimitados',
                'Integración con WhatsApp',
                'Soporte prioritario',
                'Estadísticas avanzadas',
                'SEO optimizado',
            ],
            'is_featured' => true,
            'is_active' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    |
    | Supported gateways and offline methods.
    |
    */
    'payment_methods' => [
        [
            'id' => 'bank_transfer',
            'type' => 'offline',
            'name' => 'Transferencia Bancaria',
            'description' => 'BAC, Banco Atlántida, Ficohsa',
            'instructions_key' => 'payment.bank_instructions',
        ],
        // Future: Stripe, Paddle, etc.
    ],

];
