<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\Shared\Services\TenantContext;
use App\Filament\App\Widgets\RevenueChart;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

final class VerifiedRevenueWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_chart_uses_only_verified_tenant_scoped_outcomes(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        DB::table('interactions')->insert([
            'tenant_id' => $tenant->getKey(),
            'type' => 'purchase',
            'metadata' => json_encode(['value' => 999999, 'currency' => 'USD'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertOutcome((string) $tenant->getKey(), 'order:1:paid', 1234);
        $this->insertOutcome((string) $other->getKey(), 'order:2:paid', 999999);

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, isQuiet: true);
        app(TenantContext::class)->setTenant((string) $tenant->getKey());
        $method = new ReflectionMethod(RevenueChart::class, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke(new RevenueChart);

        self::assertSame(12.34, array_sum($data['datasets'][0]['data']));
        self::assertSame('Ingresos verificados netos (L.)', $data['datasets'][0]['label']);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    private function insertOutcome(string $tenantId, string $key, int $amountMinor): void
    {
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'order',
            'aggregate_id' => explode(':', $key)[1],
            'outcome' => CommercialOutcome::OrderPaid->value,
            'projection_key' => $key,
            'amount_minor' => $amountMinor,
            'currency' => 'HNL',
            'source_occurred_at' => now(),
            'source_version' => 1,
            'metadata' => json_encode(['authority' => 'server_aggregate'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
