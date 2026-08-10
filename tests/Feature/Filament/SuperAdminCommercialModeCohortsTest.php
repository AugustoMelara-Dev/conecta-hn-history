<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Infrastructure\TenantGovernance\SuperAdmin\SuperAdminDashboardBootstrapper;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SuperAdminCommercialModeCohortsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrapper_populates_activation_cohorts_grouped_by_commercial_mode(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'commercial_mode')) {
            $this->markTestSkipped('tenants.commercial_mode is required for superadmin cohort bootstrap test.');
        }

        Tenant::query()
            ->whereIn('slug', ['tenant-products', 'tenant-appointments', 'tenant-consulting'])
            ->delete();

        Tenant::factory()->create([
            'name' => 'Tenant Products',
            'slug' => 'tenant-products',
            'is_active' => true,
            'business_type' => 'retail',
            'commercial_mode' => 'products',
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
        ]);

        Tenant::factory()->create([
            'name' => 'Tenant Appointments',
            'slug' => 'tenant-appointments',
            'is_active' => true,
            'business_type' => 'service',
            'commercial_mode' => 'appointments',
            'plan_type' => 'pro',
            'subscription_status' => 'active',
        ]);

        Tenant::factory()->create([
            'name' => 'Tenant Consulting',
            'slug' => 'tenant-consulting',
            'is_active' => false,
            'business_type' => 'service',
            'commercial_mode' => 'consulting_leads',
            'plan_type' => 'semilla',
            'subscription_status' => 'pending',
        ]);

        $dashboard = new Dashboard;
        app(SuperAdminDashboardBootstrapper::class)->boot($dashboard);

        $labels = collect($dashboard->activationCohorts)->pluck('label')->all();

        $this->assertContains('Starter Products', $labels);
        $this->assertContains('Starter Appointments', $labels);
        $this->assertContains('Starter Consulting', $labels);

        $segments = collect($dashboard->segmentExpansion)->pluck('segment')->all();
        $this->assertContains('Starter Products', $segments);
        $this->assertContains('Starter Appointments', $segments);
        $this->assertContains('Starter Consulting', $segments);
    }
}
