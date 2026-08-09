<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Models\Tenant;
use App\Support\Product\TenantAccessMatrix;
use Tests\TestCase;

uses(TestCase::class);

it('allows retail tenant to access products module', function (): void {
    $tenant = (new Tenant)->forceFill([
        'business_type' => BusinessType::RETAIL->value,
        'is_pro' => false,
    ]);

    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_PRODUCTS))->toBeTrue();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_SERVICES))->toBeFalse();
});

it('allows service tenant to access service modules', function (): void {
    $tenant = (new Tenant)->forceFill([
        'business_type' => BusinessType::SERVICE->value,
        'is_pro' => false,
    ]);

    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_SERVICES))->toBeTrue();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_SERVICE_PROVIDERS))->toBeTrue();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_APPOINTMENTS))->toBeTrue();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_PRODUCTS))->toBeFalse();
});

it('restricts menu sections to pro retail tenants', function (): void {
    $retailFree = (new Tenant)->forceFill([
        'business_type' => BusinessType::RETAIL->value,
        'plan_type' => 'semilla',
        'subscription_status' => 'active',
    ]);

    $retailPro = (new Tenant)->forceFill([
        'business_type' => BusinessType::RETAIL->value,
        'plan_type' => 'pro',
        'subscription_status' => 'active',
    ]);

    expect(TenantAccessMatrix::tenantCanAccess($retailFree, TenantAccessMatrix::MODULE_MENU_SECTIONS))->toBeFalse();
    expect(TenantAccessMatrix::tenantCanAccess($retailPro, TenantAccessMatrix::MODULE_MENU_SECTIONS))->toBeTrue();
});

it('keeps consulting leads out of catalog and appointment operations by default', function (): void {
    $tenant = (new Tenant)->forceFill([
        'business_type' => BusinessType::SERVICE->value,
        'commercial_mode' => CommercialMode::CONSULTING_LEADS->value,
    ]);

    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_PRODUCTS))->toBeFalse();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_SERVICES))->toBeFalse();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_APPOINTMENTS))->toBeFalse();
});

it('exposes both product and appointment operations for hybrid tenants without an allowlist', function (): void {
    config()->set('conecta.features.hybrid_commercial_mode_tenants', []);

    $tenant = (new Tenant)->forceFill([
        'id' => 'tenant-hybrid-test',
        'slug' => 'tenant-hybrid-test',
        'business_type' => BusinessType::SERVICE->value,
        'commercial_mode' => CommercialMode::HYBRID->value,
    ]);

    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_PRODUCTS))->toBeTrue();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_SERVICES))->toBeTrue();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_SERVICE_PROVIDERS))->toBeTrue();
    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_APPOINTMENTS))->toBeTrue();
});

it('gates pro-only menu sections with the canonical subscription entitlement', function (
    string $status,
    ?string $endsAt,
    bool $allowed,
): void {
    $tenant = (new Tenant)->forceFill([
        'business_type' => BusinessType::RETAIL->value,
        'commercial_mode' => CommercialMode::PRODUCTS->value,
        'plan_type' => 'pro',
        'subscription_status' => $status,
        'subscription_ends_at' => $endsAt,
    ]);

    expect(TenantAccessMatrix::tenantCanAccess($tenant, TenantAccessMatrix::MODULE_MENU_SECTIONS))
        ->toBe($allowed);
})->with([
    'active without expiry' => ['active', null, true],
    'trial with future expiry' => ['trial', now()->addDay()->toDateTimeString(), true],
    'active past expiry' => ['active', now()->subDay()->toDateTimeString(), false],
    'canceled' => ['canceled', now()->addDay()->toDateTimeString(), false],
    'past due' => ['past_due', now()->addDay()->toDateTimeString(), false],
    'expired' => ['expired', now()->addDay()->toDateTimeString(), false],
]);
