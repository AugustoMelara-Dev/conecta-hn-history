<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Tenant;

// ═══════════════════════════════════════════════════════════════════════
// UNIT TESTS - BusinessType Enum and Tenant Model Integration
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: BusinessType enum exists with correct cases
it('BusinessType enum has correct cases', function () {
    expect(BusinessType::cases())->toHaveCount(2);
    expect(BusinessType::RETAIL->value)->toBe('retail');
    expect(BusinessType::SERVICE->value)->toBe('service');
});

// TEST 2: BusinessType has labels for Filament
it('BusinessType has labels for Filament', function () {
    expect(BusinessType::RETAIL->getLabel())->toBe('Comercio (Retail)');
    expect(BusinessType::SERVICE->getLabel())->toBe('Servicios / Reservas');
});

// TEST 3: BusinessType has icons
it('BusinessType has icons', function () {
    expect(BusinessType::RETAIL->getIcon())->toBe('heroicon-o-shopping-bag');
    expect(BusinessType::SERVICE->getIcon())->toBe('heroicon-o-calendar');
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
