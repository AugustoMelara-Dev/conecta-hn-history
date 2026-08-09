<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Application\Reporting\ReportRegistry;
use App\Application\Reporting\Reports\MrrArrReport;
use App\Application\Reporting\Reports\RevenueByMonthReport;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReportSqlPortabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['order_items', 'products', 'orders', 'subscription_payments', 'crm_activities', 'crm_contacts', 'crm_deals', 'crm_pipelines', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('orders', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('status');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('customer_email')->nullable();
            $table->string('channel')->nullable();
            $table->timestamps();
        });
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('status')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('lifecycle_stage')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->timestamps();
        });
        Schema::create('crm_pipelines', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
        });
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id')->nullable();
            $table->string('owner_user_id')->nullable();
            $table->string('status');
            $table->decimal('value', 12, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->string('order_id');
            $table->string('product_id');
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
        });
    }

    #[Test]
    public function sqlite_reports_sum_and_order_months_inside_the_exact_tenant(): void
    {
        DB::table('orders')->insert([
            $this->order('o-1', 'tenant-a', '2026-01-10 12:00:00', 100),
            $this->order('o-2', 'tenant-a', '2026-01-20 12:00:00', 50),
            $this->order('o-3', 'tenant-a', '2026-02-05 12:00:00', 90),
            $this->order('o-foreign', 'tenant-b', '2026-01-15 12:00:00', 999),
        ]);
        DB::table('subscription_payments')->insert([
            $this->payment('p-1', 'tenant-a', '2026-01-03 12:00:00', 20),
            $this->payment('p-2', 'tenant-a', '2026-02-03 12:00:00', 40),
            $this->payment('p-foreign', 'tenant-b', '2026-01-03 12:00:00', 999),
        ]);

        $range = ReportRange::custom(
            CarbonImmutable::parse('2026-01-01 00:00:00'),
            CarbonImmutable::parse('2026-02-28 23:59:59'),
        );

        $revenue = (new RevenueByMonthReport)->getData('tenant-a', $range);
        self::assertSame(['2026-01', '2026-02'], array_map(static fn ($row): string => $row->label, $revenue->rows));
        self::assertSame([150.0, 90.0], array_map(static fn ($row): float => $row->value, $revenue->rows));

        $mrr = (new MrrArrReport)->getData('tenant-a', $range);
        self::assertSame(['2026-01', '2026-02'], array_map(static fn ($row): string => $row->label, $mrr->rows));
        self::assertSame([20.0, 40.0], array_map(static fn ($row): float => $row->value, $mrr->rows));
    }

    #[Test]
    public function all_registered_reports_execute_against_an_empty_sqlite_schema(): void
    {
        $range = ReportRange::custom(
            CarbonImmutable::parse('2026-01-01 00:00:00'),
            CarbonImmutable::parse('2026-01-31 23:59:59'),
        );

        foreach (app(ReportRegistry::class)->all() as $report) {
            self::assertIsArray($report->getData('tenant-empty', $range)->toArray(), $report->getKey()->value);
        }
    }

    /** @return array<string, mixed> */
    private function order(string $id, string $tenantId, string $createdAt, int $amount): array
    {
        return ['id' => $id, 'tenant_id' => $tenantId, 'status' => 'paid', 'total_amount' => $amount, 'created_at' => $createdAt, 'updated_at' => $createdAt];
    }

    /** @return array<string, mixed> */
    private function payment(string $id, string $tenantId, string $createdAt, int $amount): array
    {
        return ['id' => $id, 'tenant_id' => $tenantId, 'status' => 'paid', 'amount' => $amount, 'created_at' => $createdAt, 'updated_at' => $createdAt];
    }
}
