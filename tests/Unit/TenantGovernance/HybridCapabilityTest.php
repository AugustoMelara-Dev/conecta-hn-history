<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Infrastructure\TenantGovernance\Capabilities\ConfigCapabilityManager;
use App\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);

it('grants complete base product and appointment capabilities to hybrid tenants without paid leakage', function (): void {
    config()->set('conecta.features.hybrid_commercial_mode_tenants', []);

    $tenant = (new Tenant)->forceFill([
        'id' => '019c1234-5678-7000-8000-000000000099',
        'slug' => 'negocio-hibrido',
        'business_type' => BusinessType::SERVICE->value,
        'commercial_mode' => CommercialMode::HYBRID->value,
        'plan_type' => TenantPlan::SEMILLA->value,
        'subscription_status' => 'active',
    ]);

    $capabilities = (new ConfigCapabilityManager)->allForTenant($tenant);

    expect($capabilities)->toContain(
        Capability::CATALOG_BASIC,
        Capability::CHECKOUT_BASIC,
        Capability::CUSTOMER_DIRECTORY,
        Capability::STAFF_BASIC,
        Capability::APPOINTMENT_BOOKING,
        Capability::STAFF_CALENDAR,
        Capability::APPOINTMENT_REMINDERS,
    )->not->toContain(
        Capability::ANALYTICS_BASIC,
        Capability::COUPONS_BASIC,
        Capability::AI_CONTENT,
    );
});

it('grants paid capabilities only while a paid subscription is actually entitled', function (
    string $status,
    ?string $endsAt,
    bool $shouldHavePaidCapabilities,
): void {
    $tenant = (new Tenant)->forceFill([
        'id' => '019c1234-5678-7000-8000-000000000100',
        'slug' => 'negocio-pro',
        'business_type' => BusinessType::SERVICE->value,
        'commercial_mode' => CommercialMode::HYBRID->value,
        'plan_type' => TenantPlan::PRO->value,
        'subscription_status' => $status,
        'subscription_ends_at' => $endsAt,
    ]);

    $capabilities = (new ConfigCapabilityManager)->allForTenant($tenant);

    expect(in_array(Capability::AI_CONTENT, $capabilities, true))->toBe($shouldHavePaidCapabilities)
        ->and(in_array(Capability::ANALYTICS_BASIC, $capabilities, true))->toBe($shouldHavePaidCapabilities)
        ->and(in_array(Capability::PAYMENTS_BASIC, $capabilities, true))->toBe($shouldHavePaidCapabilities);
})->with([
    'active without expiry' => ['active', null, true],
    'trial with future expiry' => ['trial', now()->addDay()->toDateTimeString(), true],
    'active with expired date' => ['active', now()->subDay()->toDateTimeString(), false],
    'expired' => ['expired', now()->addDay()->toDateTimeString(), false],
    'canceled' => ['canceled', now()->addDay()->toDateTimeString(), false],
    'past due' => ['past_due', now()->addDay()->toDateTimeString(), false],
]);
