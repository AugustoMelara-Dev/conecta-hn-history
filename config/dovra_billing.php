<?php

declare(strict_types=1);

return [
    'usage_alert_thresholds' => [70, 90, 100],

    'price_books' => [
        'hn' => [
            'version' => 'research-v1',
            'currency' => 'HNL',
            'plans' => [
                'explore' => [
                    'monthly_minor' => 0,
                    'annual_minor' => 0,
                    'sandbox_only' => true,
                    'live_entitled' => false,
                    'included_credits' => 0.0,
                    'capabilities' => ['ai.sandbox_agent'],
                    'checkout_mode' => 'external',
                ],
                'launch' => [
                    'monthly_minor' => 4900,
                    'annual_minor' => 48804,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 25.0,
                    'capabilities' => ['ai.live_agent', 'billing.portal'],
                    'checkout_mode' => 'external',
                ],
                'grow' => [
                    'monthly_minor' => 14900,
                    'annual_minor' => 148404,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 100.0,
                    'capabilities' => ['ai.live_agent', 'channels.multiple'],
                    'checkout_mode' => 'external',
                ],
                'scale' => [
                    'monthly_minor' => 39900,
                    'annual_minor' => 397404,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 300.0,
                    'capabilities' => ['ai.live_agent', 'voice', 'api'],
                    'checkout_mode' => 'external',
                ],
                'enterprise' => [
                    'monthly_minor' => 150000,
                    'annual_minor' => 1494000,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 1000.0,
                    'capabilities' => ['ai.live_agent', 'voice', 'api', 'support.priority'],
                    'checkout_mode' => 'external',
                ],
            ],
        ],
    ],

    'market_providers' => [
        'hn' => [
            'provider' => 'deterministic-fake',
            'checkout_mode' => 'external',
            'signing_secret' => 'hn-secret',
        ],
    ],

    'runtime' => [
        'adapter' => \App\Infrastructure\Billing\Global\GlobalBillingRuntimeAdapter::class,
        'entitlement_authority' => \App\Application\Billing\Global\GlobalEntitlementService::class,
        'credit_ledger_authority' => \App\Domain\Billing\Global\CreditLedgerInterface::class,
        'legacy_adoption' => 'not_migrated',
    ],
];
