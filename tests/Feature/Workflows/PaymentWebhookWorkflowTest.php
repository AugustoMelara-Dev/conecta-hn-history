<?php

use App\Domain\ValueObjects\Payment\PaymentWebhook;

test('payment webhook value object extracts canonical billing fields', function () {
    $webhook = PaymentWebhook::fromArray('payment.success', [
        'id' => 'evt_123',
        'meta' => [
            'tenant_id' => 'tenant_123',
            'provider' => 'stripe',
        ],
        'data' => [
            'transaction_id' => 'tx_123',
            'object' => [
                'id' => 'tx_123',
                'amount' => 9900,
                'currency' => 'HNL',
                'metadata' => [
                    'tenant_id' => 'tenant_123',
                ],
            ],
        ],
    ]);

    expect($webhook->tenantId)->toBe('tenant_123')
        ->and($webhook->providerId)->toBe('evt_123')
        ->and($webhook->paymentReference())->toBe('tx_123')
        ->and($webhook->amountInCents())->toBe(9900)
        ->and($webhook->currency())->toBe('HNL')
        ->and($webhook->providerName())->toBe('stripe');
});

test('payment webhook value object rejects missing provider event id', function () {
    PaymentWebhook::fromArray('payment.success', [
        'meta' => [
            'tenant_id' => 'tenant_123',
            'provider' => 'stripe',
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Missing provider event ID');
