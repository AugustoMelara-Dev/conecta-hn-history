<?php

use App\Models\Payment\InboundWebhook;
use App\Jobs\Payment\ProcessPaymentWebhook;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('happy path: persists webhook and dispatches job', function () {
    Bus::fake();

    $payload = [
        'id' => 'evt_test_123',
        'type' => 'payment.success',
        'data' => [
            'object' => [
                'metadata' => ['tenant_id' => 'tenant_A']
            ]
        ]
    ];

    postJson('/api/webhook/payment', $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'queued']);

    $this->assertDatabaseHas('inbound_webhooks', [
        'provider_transaction_id' => 'evt_test_123',
        'status' => 'pending'
    ]);

    Bus::assertDispatched(ProcessPaymentWebhook::class, function ($job) {
        $webhook = InboundWebhook::where('provider_transaction_id', 'evt_test_123')->first();
        return $job->webhookId === $webhook->id;
    });
});

test('idempotency: ignores duplicate webhooks', function () {
    Bus::fake();

    InboundWebhook::create([
        'tenant_id' => 'tenant_A',
        'provider' => 'stripe',
        'event_type' => 'payment.success',
        'provider_transaction_id' => 'evt_duplicate',
        'payload' => [],
        'status' => 'completed'
    ]);

    $payload = [
        'id' => 'evt_duplicate',
        'type' => 'payment.success', 
        'data' => []
    ];

    postJson('/api/webhook/payment', $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'acknowledged_duplicate']);

    Bus::assertNotDispatched(ProcessPaymentWebhook::class);
});
