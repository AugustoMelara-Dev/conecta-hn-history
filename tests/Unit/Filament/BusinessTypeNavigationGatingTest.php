<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\ProductResource;
use App\Filament\App\Resources\ServiceProviderResource;
use App\Filament\App\Resources\ServiceResource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Tests\TestCase;

final class BusinessTypeNavigationGatingTest extends TestCase
{
    public function test_products_mode_navigation_for_products(): void
    {
        $tenant = new Tenant([
            'business_type' => BusinessType::RETAIL->value,
            'commercial_mode' => CommercialMode::PRODUCTS->value,
        ]);
        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $this->assertTrue(ProductResource::canViewAny());
        $this->assertTrue(ProductResource::shouldRegisterNavigation());
        $this->assertFalse(ServiceResource::canViewAny());
        $this->assertFalse(ServiceProviderResource::canViewAny());
        $this->assertFalse(AppointmentResource::canViewAny());
    }

    public function test_appointments_mode_navigation_for_services_and_appointments(): void
    {
        $tenant = new Tenant([
            'business_type' => BusinessType::SERVICE->value,
            'commercial_mode' => CommercialMode::APPOINTMENTS->value,
        ]);
        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $this->assertFalse(ProductResource::canViewAny());
        $this->assertTrue(ServiceResource::canViewAny());
        $this->assertTrue(ServiceProviderResource::canViewAny());
        $this->assertTrue(AppointmentResource::canViewAny());
    }

    public function test_retail_hides_service_navigation(): void
    {
        $tenant = new Tenant(['business_type' => BusinessType::RETAIL->value]);
        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $this->assertFalse(ServiceResource::canViewAny());
        $this->assertFalse(ServiceProviderResource::canViewAny());
        $this->assertFalse(AppointmentResource::canViewAny());
    }

    public function test_consulting_leads_hide_catalog_and_appointment_navigation(): void
    {
        $tenant = new Tenant([
            'business_type' => BusinessType::SERVICE->value,
            'commercial_mode' => CommercialMode::CONSULTING_LEADS->value,
        ]);
        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $this->assertFalse(ProductResource::canViewAny());
        $this->assertFalse(ServiceResource::canViewAny());
        $this->assertFalse(ServiceProviderResource::canViewAny());
        $this->assertFalse(AppointmentResource::canViewAny());
    }

    public function test_hybrid_navigation_requires_tenant_allowlist(): void
    {
        config()->set('conecta.features.hybrid_commercial_mode_tenants', []);

        $tenant = (new Tenant())->forceFill([
            'id' => 'tenant-hybrid-nav',
            'slug' => 'tenant-hybrid-nav',
            'business_type' => BusinessType::SERVICE->value,
            'commercial_mode' => CommercialMode::HYBRID->value,
        ]);
        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $this->assertFalse(ProductResource::canViewAny());
        $this->assertFalse(ServiceResource::canViewAny());
        $this->assertFalse(ServiceProviderResource::canViewAny());
        $this->assertFalse(AppointmentResource::canViewAny());
    }

    public function test_hybrid_navigation_exposes_both_sides_for_allowlisted_tenant(): void
    {
        config()->set('conecta.features.hybrid_commercial_mode_tenants', ['tenant-hybrid-nav']);

        $tenant = (new Tenant())->forceFill([
            'id' => 'tenant-hybrid-nav',
            'slug' => 'tenant-hybrid-nav',
            'business_type' => BusinessType::SERVICE->value,
            'commercial_mode' => CommercialMode::HYBRID->value,
        ]);
        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $this->assertTrue(ProductResource::canViewAny());
        $this->assertTrue(ServiceResource::canViewAny());
        $this->assertTrue(ServiceProviderResource::canViewAny());
        $this->assertTrue(AppointmentResource::canViewAny());
    }
}
