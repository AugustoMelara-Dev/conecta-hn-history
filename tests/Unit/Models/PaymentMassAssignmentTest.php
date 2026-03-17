<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * VC-AUDIT-002 Verification: Payment Mass Assignment Protection
 *
 * This test verifies that tenant_id cannot be mass-assigned via
 * request payloads, preventing cross-tenant billing fraud.
 */
test('payment model guards tenant_id against mass assignment', function () {
    // Arrange: Create two tenants
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    // Act: Set tenant context to tenant1 and create a payment without tenant_id
    $this->actingAsTenant((string) $tenant1->id);

    $payment = Payment::create([
        'id' => (string) Str::uuid(),
        'amount' => 99.99,
        'status' => 'pending',
        'reference_number' => 'REF-TEST-001',
        'bank' => 'Test Bank',
        'proof_path' => 'payments/proof-mock.jpg',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    // Assert: tenant_id should be injected from context (tenant1)
    expect($payment->tenant_id)->toBe($tenant1->id)
        ->and($payment->tenant_id)->not->toBe($tenant2->id);
});

test('payment tenant_id is in guarded array', function () {
    $payment = new Payment();
    $guarded = $payment->getGuarded();

    expect($guarded)->toContain('tenant_id');
});

test('payment tenant_id is not in fillable array', function () {
    $payment = new Payment();
    $fillable = $payment->getFillable();

    expect($fillable)->not->toContain('tenant_id');
});
