<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

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

    // Act: Set tenant context to tenant1 and attempt to create payment for tenant2
    app(TenantContext::class)->setTenant($tenant1->id);

    expect(fn () => Payment::create([
        'tenant_id' => $tenant2->id,  // Malicious: attempt cross-tenant
        'amount' => 99.99,
        'status' => 'pending',
        'reference_number' => 'REF-TEST-001',
        'bank' => 'Test Bank',
    ]))->toThrow(MassAssignmentException::class);
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
