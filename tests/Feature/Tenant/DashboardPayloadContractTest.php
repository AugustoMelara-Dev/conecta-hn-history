<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Shared\Services\TenantContext as RuntimeTenantContext;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Infrastructure\Tenant\EloquentDashboardMetricsRepository;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DashboardPayloadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_contract_includes_commercial_mode_kpis_without_react_dashboard(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'commercial_mode') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('commercial_mode dashboard metrics schema is required in this local testing profile.');
        }

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'commercial_mode' => CommercialMode::CONSULTING_LEADS->value,
            'city_id' => City::factory()->create()->id,
        ]);

        DB::table('leads')->insert([
            [
                'tenant_id' => $tenant->id,
                'name' => 'Prospecto calificado',
                'status' => 'qualified',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Prospecto nuevo',
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(RuntimeTenantContext::class)->setTenant((string) $tenant->id);

        $summary = app(EloquentDashboardMetricsRepository::class)->getKpiSummary((string) $tenant->id);

        $this->assertSame('consulting_leads', $summary['commercial_mode']);
        $this->assertSame('Starter Consulting', $summary['commercial_mode_label']);
        $this->assertSame('Consultas calificadas', $summary['commercial_mode_kpis']['headline']);
        $this->assertSame(1, $summary['commercial_mode_kpis']['primary_value']);
        $this->assertSame(2, $summary['commercial_mode_kpis']['secondary_value']);
        $this->assertTrue($summary['commercial_mode_kpis']['activation_ready']);
    }
}
