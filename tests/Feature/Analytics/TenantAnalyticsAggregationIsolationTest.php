<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsReader;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Jobs\Analytics\SyncAnalyticsJob;
use App\Infrastructure\Jobs\Analytics\SyncTenantAnalytics;
use App\Models\Interaction;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TenantAnalyticsAggregationIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();

        parent::tearDown();
    }

    public function test_daily_job_aggregates_only_the_uuid_tenant_in_worker_context(): void
    {
        app(TenantContext::class)->setAsSystemRoot();
        $tenant = Tenant::factory()->create(['status' => 'approved']);
        $otherTenant = Tenant::factory()->create(['status' => 'approved']);
        $date = '2026-07-20';

        $this->insertVisit((string) $tenant->id, 'visitor-a', $date.' 08:00:00');
        $this->insertVisit((string) $tenant->id, 'visitor-a', $date.' 09:00:00');
        $this->insertVisit((string) $tenant->id, 'visitor-b', $date.' 10:00:00');
        $this->insertVisit((string) $otherTenant->id, 'visitor-foreign', $date.' 11:00:00');
        $this->insertInteraction((string) $tenant->id, Interaction::TYPE_WHATSAPP_CLICK, $date.' 12:00:00');
        $this->insertInteraction((string) $tenant->id, Interaction::TYPE_CALL_CLICK, $date.' 13:00:00');
        $this->insertInteraction((string) $otherTenant->id, Interaction::TYPE_WHATSAPP_CLICK, $date.' 14:00:00');

        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $tenant->id);

        (new SyncTenantAnalytics((string) $tenant->id, $date))->handle();

        $aggregate = DB::table('tenant_analytics')
            ->where('tenant_id', $tenant->id)
            ->whereDate('date', $date)
            ->first();

        $this->assertNotNull($aggregate);
        $this->assertSame(3, (int) $aggregate->views);
        $this->assertSame(2, (int) $aggregate->unique_visitors);
        $this->assertSame(1, (int) $aggregate->whatsapp_clicks);
        $this->assertSame(0, DB::table('tenant_analytics')
            ->where('tenant_id', $otherTenant->id)
            ->whereDate('date', $date)
            ->count());
    }

    public function test_reader_uses_canonical_columns_and_hides_foreign_aggregate(): void
    {
        app(TenantContext::class)->setAsSystemRoot();
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $date = '2026-07-20';

        DB::table('tenant_analytics')->insert([
            [
                'tenant_id' => $tenant->id,
                'date' => $date,
                'views' => 8,
                'whatsapp_clicks' => 3,
                'unique_visitors' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $otherTenant->id,
                'date' => $date,
                'views' => 900,
                'whatsapp_clicks' => 900,
                'unique_visitors' => 900,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $tenant->id);

        $rows = app(AnalyticsReader::class)->getAnalyticsData(
            (string) $tenant->id,
            Carbon::parse($date)->startOfDay(),
            Carbon::parse($date)->endOfDay(),
        );

        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows->first()['visits']);
        $this->assertSame(8, $rows->first()['total']);
        $this->assertSame(3, $rows->first()['whatsapp_clicks']);
    }

    public function test_coordinator_dispatches_uuid_jobs_only_for_approved_tenants(): void
    {
        app(TenantContext::class)->setAsSystemRoot();
        $approvedTenant = Tenant::factory()->create(['status' => 'approved']);
        $pendingTenant = Tenant::factory()->create(['status' => 'pending']);
        Queue::fake();

        (new SyncAnalyticsJob)->handle();

        Queue::assertPushed(SyncTenantAnalytics::class, function (SyncTenantAnalytics $job) use ($approvedTenant): bool {
            return $job->tenantId === (string) $approvedTenant->id;
        });
        Queue::assertNotPushed(SyncTenantAnalytics::class, function (SyncTenantAnalytics $job) use ($pendingTenant): bool {
            return $job->tenantId === (string) $pendingTenant->id;
        });
    }

    private function insertVisit(string $tenantId, string $sessionHash, string $createdAt): void
    {
        DB::table('tenant_visits')->insert([
            'tenant_id' => $tenantId,
            'session_hash' => $sessionHash,
            'device_type' => 'desktop',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertInteraction(string $tenantId, string $type, string $createdAt): void
    {
        DB::table('interactions')->insert([
            'tenant_id' => $tenantId,
            'type' => $type,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
