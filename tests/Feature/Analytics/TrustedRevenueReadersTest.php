<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Sales\EloquentReportReader;
use App\Infrastructure\Tenant\EloquentDashboardMetricsRepository;
use App\Models\City;
use App\Models\Order;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TrustedRevenueReadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_report_ignore_unverified_orders_and_cross_tenant_or_foreign_currency_outcomes(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        $tenant = Tenant::factory()->create(['city_id' => City::factory()->create()->id]);
        $other = Tenant::factory()->create();
        app(TenantContext::class)->setTenant((string) $tenant->getKey());

        Order::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'status' => Order::STATUS_DELIVERED,
            'total' => '9999.99',
        ]);

        $this->insertOutcome((string) $tenant->getKey(), '1', CommercialOutcome::OrderPaid, 2500, 'HNL');
        $this->insertOutcome((string) $tenant->getKey(), '1', CommercialOutcome::OrderRefunded, -500, 'HNL');
        $this->insertOutcome((string) $tenant->getKey(), '2', CommercialOutcome::OrderPaid, 100000, 'USD');
        $this->insertOutcome((string) $other->getKey(), '3', CommercialOutcome::OrderPaid, 900000, 'HNL');

        $dashboard = app(EloquentDashboardMetricsRepository::class)->getKpiSummary((string) $tenant->getKey());
        $report = app(EloquentReportReader::class)->getSalesSummary(
            (string) $tenant->getKey(),
            now()->startOfDay(),
            now()->endOfDay(),
            collect([['total' => 999999.99]]),
        );

        self::assertSame(20.0, $dashboard['today_revenue']);
        self::assertSame('HNL', $dashboard['currency']);
        self::assertSame(20.0, $report['total_revenue']);
        self::assertSame('HNL', $report['currency']);
        self::assertSame(1, $report['order_count']);
        self::assertSame(20.0, $report['average_order']);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function insertOutcome(
        string $tenantId,
        string $aggregateId,
        CommercialOutcome $outcome,
        int $amountMinor,
        string $currency,
    ): void {
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'order',
            'aggregate_id' => $aggregateId,
            'outcome' => $outcome->value,
            'projection_key' => "{$tenantId}:order:{$aggregateId}:{$outcome->value}",
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'source_occurred_at' => now(),
            'source_version' => 1,
            'metadata' => json_encode(['authority' => 'server_aggregate'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
