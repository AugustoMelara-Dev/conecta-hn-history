<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Application\Reporting\ReportRegistry;
use App\Application\Reporting\Reports\AttributionBySourceReport;
use App\Application\Reporting\Reports\AvgOrderValueReport;
use App\Application\Reporting\Reports\ChannelPerformanceReport;
use App\Application\Reporting\Reports\ChurnRateReport;
use App\Application\Reporting\Reports\CustomerLtvReport;
use App\Application\Reporting\Reports\RefundRateReport;
use App\Application\Reporting\Reports\RevenueByMonthReport;
use App\Application\Reporting\Reports\TopProductsReport;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReportSqlPortabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $foreignTenant;

    private ReportRange $range;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->foreignTenant = Tenant::factory()->create();
        $this->range = ReportRange::custom(
            CarbonImmutable::parse('2026-01-01 00:00:00'),
            CarbonImmutable::parse('2026-02-28 23:59:59'),
        );
    }

    #[Test]
    public function canonical_order_reports_use_total_payment_status_source_and_exact_tenant(): void
    {
        $this->order($this->tenant->id, 'a-jan-1', 'buyer@example.test', '2026-01-10 12:00:00', 100, 'paid', null);
        $this->order($this->tenant->id, 'a-jan-2', 'buyer@example.test', '2026-01-20 12:00:00', 50, 'paid', 'web');
        $this->order($this->tenant->id, 'a-feb', 'repeat@example.test', '2026-02-05 12:00:00', 90, 'paid', 'store');
        $this->order($this->tenant->id, 'a-refund', 'refund@example.test', '2026-02-06 12:00:00', 40, 'refunded', 'web');
        $this->order($this->tenant->id, 'a-pending', 'pending@example.test', '2026-02-07 12:00:00', 500, 'pending', 'web');
        $this->order($this->foreignTenant->id, 'b-foreign', 'buyer@example.test', '2026-01-15 12:00:00', 999, 'paid', 'web');

        $revenue = (new RevenueByMonthReport)->getData($this->tenant->id, $this->range);
        self::assertSame(['2026-01', '2026-02'], array_column($revenue->toArray()['rows'], 'label'));
        self::assertSame([150.0, 90.0], array_column($revenue->toArray()['rows'], 'value'));
        self::assertSame(['total_revenue' => 240.0, 'total_orders' => 3, 'avg_per_month' => 120.0], $revenue->summary);

        $aov = (new AvgOrderValueReport)->getData($this->tenant->id, $this->range);
        self::assertSame(80.0, $aov->rows[0]->value);

        $channels = (new ChannelPerformanceReport)->getData($this->tenant->id, $this->range);
        self::assertSame(['direct', 'store', 'web'], array_column($channels->toArray()['rows'], 'label'));
        self::assertSame([100.0, 90.0, 50.0], array_column($channels->toArray()['rows'], 'value'));

        $ltv = (new CustomerLtvReport)->getData($this->tenant->id, $this->range);
        self::assertSame(['Recurrente', 'Nuevo'], array_column($ltv->toArray()['rows'], 'label'));
        self::assertSame([150.0, 90.0], array_column($ltv->toArray()['rows'], 'value'));

        $refund = (new RefundRateReport)->getData($this->tenant->id, $this->range);
        self::assertSame(25.0, $refund->rows[0]->value);
    }

    #[Test]
    public function attribution_is_tenant_scoped_and_falls_back_to_direct(): void
    {
        $email = 'shared@example.test';
        $this->contact($this->tenant->id, $email, null);
        $this->contact($this->foreignTenant->id, $email, 'foreign-source');
        $this->contact($this->tenant->id, 'social@example.test', 'social');
        $this->order($this->tenant->id, 'a-direct', $email, '2026-01-10 12:00:00', 125, 'paid', 'web');
        $this->order($this->tenant->id, 'a-social', 'social@example.test', '2026-01-11 12:00:00', 75, 'paid', 'web');
        $this->order($this->foreignTenant->id, 'b-shared', $email, '2026-01-12 12:00:00', 900, 'paid', 'web');

        $data = (new AttributionBySourceReport)->getData($this->tenant->id, $this->range)->toArray();

        self::assertSame(['direct', 'social'], array_column($data['rows'], 'label'));
        self::assertSame([125.0, 75.0], array_column($data['rows'], 'value'));
    }

    #[Test]
    public function top_products_uses_line_total_and_paid_orders_only(): void
    {
        $first = $this->product($this->tenant->id, 'Alpha');
        $second = $this->product($this->tenant->id, 'Beta');
        $paid = $this->order($this->tenant->id, 'a-paid', 'buyer@example.test', '2026-01-10 12:00:00', 150, 'paid', 'web');
        $pending = $this->order($this->tenant->id, 'a-pending', 'buyer@example.test', '2026-01-11 12:00:00', 800, 'pending', 'web');
        $this->orderItem($paid, $first, 2, 120);
        $this->orderItem($paid, $second, 1, 30);
        $this->orderItem($pending, $second, 10, 800);

        $data = (new TopProductsReport)->getData($this->tenant->id, $this->range)->toArray();

        self::assertSame(['Alpha', 'Beta'], array_column($data['rows'], 'label'));
        self::assertSame([120.0, 30.0], array_column($data['rows'], 'value'));
        self::assertSame([2, 1], array_column(array_column($data['rows'], 'metadata'), 'units'));
    }

    #[Test]
    public function churn_is_the_paid_start_cohort_that_did_not_return_in_range(): void
    {
        $this->order($this->tenant->id, 'a-lost-before', 'lost@example.test', '2025-12-10 12:00:00', 40, 'paid', 'web');
        $this->order($this->tenant->id, 'a-kept-before', 'kept@example.test', '2025-12-11 12:00:00', 50, 'paid', 'web');
        $this->order($this->tenant->id, 'a-kept-return', 'kept@example.test', '2026-01-11 12:00:00', 60, 'paid', 'web');
        $this->order($this->tenant->id, 'a-unpaid-before', 'unpaid@example.test', '2025-12-12 12:00:00', 70, 'pending', 'web');
        $this->order($this->tenant->id, 'a-new', 'new@example.test', '2026-01-13 12:00:00', 80, 'paid', 'web');
        $this->order($this->foreignTenant->id, 'b-same-email', 'lost@example.test', '2026-01-14 12:00:00', 900, 'paid', 'web');

        $report = new ChurnRateReport;
        $data = $report->getData($this->tenant->id, $this->range);

        self::assertStringContainsString('no regresaron', mb_strtolower($report->getDescription()));
        self::assertSame(['churn_rate' => 50.0, 'start' => 2, 'retained' => 1, 'lost' => 1], $data->summary);
        self::assertSame(50.0, $data->rows[0]->value);
    }

    #[Test]
    public function all_registered_reports_execute_against_empty_real_migrations(): void
    {
        $reports = app(ReportRegistry::class)->all();
        self::assertCount(25, $reports);

        foreach ($reports as $report) {
            self::assertIsArray($report->getData($this->tenant->id, $this->range)->toArray(), $report->getKey()->value);
        }
    }

    private function order(string $tenantId, string $number, string $email, string $createdAt, int $total, string $paymentStatus, ?string $source): int
    {
        return (int) DB::table('orders')->insertGetId([
            'tenant_id' => $tenantId,
            'order_number' => $number,
            'customer_email' => $email,
            'subtotal' => $total,
            'total' => $total,
            'payment_status' => $paymentStatus,
            'source' => $source,
            'status' => 'delivered',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function contact(string $tenantId, string $email, ?string $source): void
    {
        DB::table('crm_contacts')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'email' => $email,
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function product(string $tenantId, string $name): int
    {
        return (int) DB::table('products')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function orderItem(int $orderId, int $productId, int $quantity, int $lineTotal): void
    {
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => 'Snapshot',
            'product_price' => $lineTotal / $quantity,
            'unit_price' => $lineTotal / $quantity,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
            'total_price' => $lineTotal,
            'options_total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
