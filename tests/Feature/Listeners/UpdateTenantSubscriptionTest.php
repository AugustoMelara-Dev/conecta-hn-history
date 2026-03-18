<?php

declare(strict_types=1);

use App\Events\PaymentApproved;
use App\Listeners\UpdateTenantSubscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('listener extends subscription exactly once for approved payments', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $user->id, 'subscription_ends_at' => null]);

    $payment = SubscriptionPayment::forceCreate([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'amount' => 99,
        'bank' => 'stripe',
        'reference_number' => 'WEBHOOK-123',
        'proof_path' => '',
        'status' => SubscriptionPayment::STATUS_APPROVED,
    ]);

    $event = PaymentApproved::fromPayment(
        tenantId: $tenant->id,
        paymentId: $payment->id,
        reviewerId: null,
        daysToAdd: 30,
    );

    app(\App\Domain\Shared\Services\TenantContext::class)->setAsSystemRoot(true);

    $listener = app(UpdateTenantSubscription::class);

    $listener->handle($event);
    $payment->refresh();
    $tenant->refresh();

    expect($payment->subscription_extended_at)->not->toBeNull()
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->subscription_ends_at)->not->toBeNull();

    $originalEndDate = $tenant->subscription_ends_at;

    $listener->handle($event);
    $tenant->refresh();

    expect($tenant->subscription_ends_at?->toIso8601String())
        ->toBe($originalEndDate?->toIso8601String());
});
