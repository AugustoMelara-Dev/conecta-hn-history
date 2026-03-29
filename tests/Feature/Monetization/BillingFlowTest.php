<?php

use App\Events\PaymentApproved;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

test('subscription payment state machine accepts uuid reviewers', function () {
    Event::fake([PaymentApproved::class]);

    $tenantId = Str::uuid()->toString();
    $adminId = Str::uuid()->toString();

    $tenant = Tenant::forceCreate([
        'id' => $tenantId,
        'name' => 'Billing Test Tenant',
        'slug' => 'billing-test-'.Str::random(6),
    ]);

    User::factory()->create([
        'id' => $adminId,
        'email' => 'billing-admin@example.test',
    ]);

    $payment = SubscriptionPayment::forceCreate([
        'tenant_id' => $tenant->id,
        'amount' => 99.00,
        'bank' => 'bank_transfer',
        'reference_number' => 'REF-123456',
        'proof_path' => 'payments/proofs/test.jpg',
        'status' => SubscriptionPayment::STATUS_PENDING,
    ]);

    expect($payment->isPending())->toBeTrue();

    $payment->approve($adminId);

    $payment->refresh();
    $tenant->refresh();

    Event::assertDispatched(PaymentApproved::class);

    expect($payment->isApproved())->toBeTrue()
        ->and($payment->reviewed_by)->toBe($adminId)
        ->and($payment->reviewed_at)->not->toBeNull();
});
