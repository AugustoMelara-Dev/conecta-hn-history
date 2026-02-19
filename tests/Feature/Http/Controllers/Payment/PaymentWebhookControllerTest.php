<?php

use App\Jobs\Payment\ProcessPaymentWebhook;
use App\Application\Commands\Payment\ProcessPaymentWebhookCommand;
use Illuminate\Support\Facades\Bus;
use function Pest\Laravel\postJson;

test('controller maps request to command and dispatches job', function () {
    Bus::fake();

    $payload = [
        'type' => 'payment.success',
        'data' => [
            'object' => [
                'id' => 'tx_123',
                'metadata' => ['tenant_id' => 'uuid-123']
            ]
        ]
    ];

    postJson('/api/webhook/payment', $payload) 
        ->assertStatus(202)
        ->assertJson(['status' => 'queued']);

    Bus::assertDispatched(ProcessPaymentWebhook::class, function ($job) use ($payload) {
        return $job->command instanceof ProcessPaymentWebhookCommand
            && $job->command->eventType === 'payment.success'
            && $job->command->tenantId === 'uuid-123';
    });
});
