<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Application\Reporting\Reports\DealVelocityReport;
use App\Application\Reporting\Reports\EmployeePerformanceReport;
use App\Application\Reporting\Reports\WinRateReport;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Shared\Services\TenantContext;
use App\Filament\App\Widgets\Crm\DealForecastWidget;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmCanonicalReportingCompatibilityTest extends TestCase
{
    private const TENANT_ID = 'tenant-a';

    private const OTHER_TENANT_ID = 'tenant-b';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2026-08-08 12:00:00'));

        $context = app(TenantContext::class);
        $context->reset();
        $context->setAsSystemRoot();

        Schema::disableForeignKeyConstraints();
        foreach (['crm_deals', 'crm_stages', 'crm_pipelines', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('crm_pipelines', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('crm_stages', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->string('name');
            $table->string('key');
            $table->string('type');
            $table->unsignedTinyInteger('probability');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
        });

        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->unsignedBigInteger('crm_stage_id');
            $table->string('owner_user_id')->nullable();
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('HNL');
            $table->string('status', 16)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        $this->seedCanonicalDeals();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_deal_velocity_reads_canonical_pipeline_and_closed_columns_per_tenant(): void
    {
        $data = (new DealVelocityReport())->getData(self::TENANT_ID, $this->reportRange());

        self::assertCount(1, $data->rows);
        self::assertSame('Sales', $data->rows[0]->label);
        self::assertSame(2.5, $data->rows[0]->value);
        self::assertSame(['count' => 2], $data->rows[0]->metadata);
    }

    public function test_win_rate_uses_canonical_closed_at_for_won_and_lost_deals(): void
    {
        $data = (new WinRateReport())->getData(self::TENANT_ID, $this->reportRange());

        self::assertSame(3, $data->rows[1]->value);
        self::assertSame(2, $data->rows[2]->value);
        self::assertSame(66.67, $data->rows[0]->value);
        self::assertSame(66.67, $data->summary['win_rate']);
    }

    public function test_employee_performance_uses_canonical_owner_and_closed_columns_per_tenant(): void
    {
        $data = (new EmployeePerformanceReport())->getData(self::TENANT_ID, $this->reportRange());

        self::assertCount(1, $data->rows);
        self::assertSame('Alice', $data->rows[0]->label);
        self::assertSame(2, $data->rows[0]->value);
        self::assertSame(550.0, $data->rows[0]->metadata['revenue']);
    }

    public function test_deal_forecast_uses_stage_relation_probability_and_closed_at(): void
    {
        $tenant = new \App\Models\Tenant();
        $tenant->setRawAttributes(['id' => self::TENANT_ID]);
        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $stats = (new TestableDealForecastWidget())->stats();

        self::assertSame('L 1,000.00', $stats[0]->getValue());
        self::assertSame('L 250.00', $stats[1]->getValue());
        self::assertSame('L 300.00', $stats[2]->getValue());
    }

    private function reportRange(): ReportRange
    {
        return ReportRange::custom(
            CarbonImmutable::now()->subMonths(2)->startOfDay(),
            CarbonImmutable::now()->startOfDay(),
        );
    }

    private function seedCanonicalDeals(): void
    {
        $now = CarbonImmutable::now();

        DB::table('crm_pipelines')->insert([
            [
                'id' => 100,
                'tenant_id' => self::TENANT_ID,
                'name' => 'Sales',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 200,
                'tenant_id' => self::OTHER_TENANT_ID,
                'name' => 'Foreign sales',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('crm_stages')->insert([
            [
                'id' => 1000,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'name' => 'Open',
                'key' => 'open',
                'type' => 'open',
                'probability' => 25,
                'is_default' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 1001,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'name' => 'Won',
                'key' => 'won',
                'type' => 'won',
                'probability' => 100,
                'is_default' => false,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 1002,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'name' => 'Lost',
                'key' => 'lost',
                'type' => 'lost',
                'probability' => 0,
                'is_default' => false,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2000,
                'tenant_id' => self::OTHER_TENANT_ID,
                'crm_pipeline_id' => 200,
                'name' => 'Foreign open',
                'key' => 'open',
                'type' => 'open',
                'probability' => 90,
                'is_default' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('users')->insert([
            ['id' => 'user-a', 'name' => 'Alice'],
            ['id' => 'user-b', 'name' => 'Bob'],
        ]);

        DB::table('crm_deals')->insert([
            [
                'id' => 1,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'crm_stage_id' => 1000,
                'owner_user_id' => 'user-a',
                'title' => 'Open canonical deal',
                'value' => 1000,
                'status' => 'open',
                'closed_at' => null,
                'created_at' => $now->subDays(5),
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'crm_stage_id' => 1001,
                'owner_user_id' => 'user-a',
                'title' => 'Won current deal',
                'value' => 300,
                'status' => 'won',
                'closed_at' => $now->subDay(),
                'created_at' => $now->subDays(3),
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'crm_stage_id' => 1002,
                'owner_user_id' => 'user-a',
                'title' => 'Lost current deal',
                'value' => 200,
                'status' => 'lost',
                'closed_at' => $now->subDays(2),
                'created_at' => $now->subDays(4),
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'crm_stage_id' => 1001,
                'owner_user_id' => 'user-a',
                'title' => 'Won outside range',
                'value' => 999,
                'status' => 'won',
                'closed_at' => $now->subMonths(4),
                'created_at' => $now->subMonths(4)->subDays(2),
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'tenant_id' => self::TENANT_ID,
                'crm_pipeline_id' => 100,
                'crm_stage_id' => 1001,
                'owner_user_id' => 'user-a',
                'title' => 'Won prior month',
                'value' => 250,
                'status' => 'won',
                'closed_at' => $now->subMonth()->subDays(2),
                'created_at' => $now->subMonth()->subDays(5),
                'updated_at' => $now->subMonth()->subDays(2),
            ],
            [
                'id' => 6,
                'tenant_id' => self::OTHER_TENANT_ID,
                'crm_pipeline_id' => 200,
                'crm_stage_id' => 2000,
                'owner_user_id' => 'user-b',
                'title' => 'Foreign open deal',
                'value' => 500,
                'status' => 'open',
                'closed_at' => null,
                'created_at' => $now->subDays(2),
                'updated_at' => $now,
            ],
        ]);
    }
}

final class TestableDealForecastWidget extends DealForecastWidget
{
    public function stats(): array
    {
        return $this->getStats();
    }
}
