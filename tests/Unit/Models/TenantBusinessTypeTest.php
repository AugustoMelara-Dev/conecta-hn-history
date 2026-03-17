<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Tenant;

// ═══════════════════════════════════════════════════════════════════════
// UNIT TESTS - BusinessType Enum and Tenant Model Integration
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: BusinessType enum exists with correct cases
it('BusinessType enum has correct cases', function () {
    expect(BusinessType::cases())->toHaveCount(4);
    expect(BusinessType::RETAIL->value)->toBe('retail');
    expect(BusinessType::FOOD->value)->toBe('food');
    expect(BusinessType::SERVICE->value)->toBe('service');
    expect(BusinessType::HYBRID->value)->toBe('hybrid');
});

// TEST 4: Tenant model casts business_type to enum
it('Tenant model casts business_type to enum', function () {
    $casts = (new Tenant)->getCasts();
    expect($casts)->toHaveKey('business_type');
    expect($casts['business_type'])->toBe(BusinessType::class);
});

// TEST 5: Tenant model has business_type in fillable
it('Tenant model has business_type in fillable', function () {
    $fillable = (new Tenant)->getFillable();
    expect($fillable)->toContain('business_type');
});
