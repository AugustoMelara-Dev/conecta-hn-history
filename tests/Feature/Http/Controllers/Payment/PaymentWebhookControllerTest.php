<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('payment webhook rejects invalid signatures', function () {
    config()->set('services.payment.webhook_secret', 'test-webhook-secret');

    $response = $this->postJson('/api/webhooks/payment', [
        'id' => 'evt_invalid',
        'event' => 'payment.success',
        'meta' => ['tenant_id' => (string) Str::uuid()],
        'data' => [
            'transaction_id' => 'tx_invalid',
            'object' => ['id' => 'tx_invalid', 'metadata' => ['tenant_id' => (string) Str::uuid()]],
        ],
    ], [
        'X-Signature' => 'bad-signature',
    ]);

    $response->assertForbidden();
});

test('payment webhook materializes a single payment record and extends the tenant once', function () {
    config()->set('services.payment.webhook_secret', 'test-webhook-secret');

    $fixture = createWebhookTenantFixture();
    $eventId = (string) Str::uuid();
    $transactionId = 'tx_'.Str::lower(Str::random(12));

    $payload = [
        'id' => $eventId,
        'event' => 'payment.success',
        'meta' => [
            'tenant_id' => $fixture['tenant_id'],
            'provider' => 'stripe',
        ],
        'data' => [
            'transaction_id' => $transactionId,
            'object' => [
                'id' => $transactionId,
                'amount' => 9900,
                'currency' => 'HNL',
                'metadata' => [
                    'tenant_id' => $fixture['tenant_id'],
                ],
            ],
        ],
    ];

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $body, 'test-webhook-secret');

    $responseA = $this->call(
        'POST',
        '/api/webhooks/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Signature' => $signature,
        ],
        $body
    );

    $responseB = $this->call(
        'POST',
        '/api/webhooks/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Signature' => $signature,
        ],
        $body
    );

    $responseA->assertOk();
    $responseB->assertOk();

    $this->assertDatabaseCount('subscription_payments', 1);
    $this->assertDatabaseHas('subscription_payments', [
        'tenant_id' => $fixture['tenant_id'],
        'reference_number' => $transactionId,
        'idempotency_key' => $eventId,
        'status' => 'approved',
    ]);
    $this->assertDatabaseHas('subscription_payments', [
        'tenant_id' => $fixture['tenant_id'],
    ]);

    $tenant = DB::table('tenants')->where('id', $fixture['tenant_id'])->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->subscription_ends_at)->not->toBeNull();
});

function createWebhookTenantFixture(): array
{
    $userId = (string) Str::uuid();
    $tenantId = (string) Str::uuid();
    $email = 'webhook+'.Str::lower(Str::random(10)).'@example.test';
    $slug = 'webhook-tenant-'.Str::lower(Str::random(10));
    $now = now();

    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Webhook Test User',
        'email' => $email,
        'password' => bcrypt(Str::random(32)),
        'role' => 'seller',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $tenantPayload = [
        'id' => $tenantId,
        'user_id' => $userId,
        'name' => 'Webhook Test Tenant',
        'slug' => $slug,
        'plan' => 'SEED',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'subscription_status')) {
        $tenantPayload['subscription_status'] = 'inactive';
    }

    if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'plan_type')) {
        $tenantPayload['plan_type'] = 'semilla';
    }

    if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'subscription_ends_at')) {
        $tenantPayload['subscription_ends_at'] = null;
    }

    if (\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'is_pro')) {
        $tenantPayload['is_pro'] = false;
    }

    DB::table('tenants')->insert($tenantPayload);

    DB::table('users')->where('id', $userId)->update([
        'tenant_id' => $tenantId,
    ]);

    return [
        'user_id' => $userId,
        'tenant_id' => $tenantId,
    ];
}
