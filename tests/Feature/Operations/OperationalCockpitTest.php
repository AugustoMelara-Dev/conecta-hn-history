<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Operations\OperationalCockpit;
use App\Models\Appointment;
use App\Models\CatalogImportBatch;
use App\Models\CrmDeal;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class OperationalCockpitTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_is_action_oriented_tenant_safe_and_provider_independent(): void
    {
        [$tenant, $owner, $location] = $this->tenant('cockpit@example.test');
        [$other, $otherOwner] = $this->tenant('cockpit-other@example.test');
        $tenant->forceFill(['ai_balance' => 4.5, 'ai_reserved_balance' => 0.5])->save();

        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenant->id,
            'aggregate_type' => 'order',
            'aggregate_id' => 'outcome-order-1',
            'outcome' => 'order_paid',
            'projection_key' => 'cockpit:order-paid:1',
            'amount_minor' => 12_500,
            'currency' => 'CAD',
            'source_occurred_at' => now(),
            'source_version' => 1,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_usage_logs')->insert([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'provider' => 'offline-test',
            'model' => 'none',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost' => 0.25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pipelineId = DB::table('crm_pipelines')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Sales',
            'key' => 'cockpit-sales',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageId = DB::table('crm_stages')->insertGetId([
            'tenant_id' => $tenant->id,
            'crm_pipeline_id' => $pipelineId,
            'name' => 'Open',
            'key' => 'cockpit-open',
            'type' => 'open',
            'probability' => 25,
            'is_default' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        CrmDeal::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'crm_pipeline_id' => $pipelineId,
            'crm_stage_id' => $stageId,
            'title' => 'Aging opportunity',
            'value' => 500,
            'currency' => 'CAD',
            'status' => 'open',
            'created_at' => now()->subDays(20),
        ]);
        Order::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'order_number' => 'COCKPIT-ORDER',
            'customer_name' => 'Buyer',
            'status' => 'pending',
            'currency' => 'CAD',
            'total' => 25,
            'created_at' => now(),
        ]);
        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'scheduled_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
            'status' => 'confirmed',
            'customer_name' => 'Guest',
            'currency' => 'CAD',
            'price_minor' => 2500,
            'timezone' => 'America/Toronto',
            'local_date' => now()->addHour()->toDateString(),
        ]);
        Quote::query()->withoutGlobalScopes()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'status' => 'issued',
            'currency' => 'CAD',
            'idempotency_key' => 'cockpit-overdue-quote',
            'payload_hash' => hash('sha256', 'cockpit-overdue-quote'),
            'expires_at' => now()->subDay(),
        ]);
        CatalogImportBatch::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'created_by' => $owner->id,
            'status' => 'failed',
            'kind' => 'product',
        ]);
        Order::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $other->id,
            'order_number' => 'OTHER-ORDER',
            'customer_name' => 'Other',
            'status' => 'pending',
            'currency' => 'CAD',
            'total' => 99,
        ]);

        $snapshot = app(OperationalCockpit::class)->snapshot(
            (string) $tenant->id,
            (string) $owner->id,
        );

        self::assertSame('CAD', $snapshot->currency);
        self::assertSame(12_500, $snapshot->verifiedOutcomeMinor);
        self::assertSame(1, $snapshot->activeOpportunities);
        self::assertSame(1, $snapshot->agingOpportunities);
        self::assertSame(1, $snapshot->upcomingOrders);
        self::assertSame(1, $snapshot->upcomingReservations);
        self::assertSame(1, $snapshot->overdueQuotes);
        self::assertSame(1, $snapshot->failedImports);
        self::assertSame(4.0, $snapshot->aiAllowance);
        self::assertSame(0.25, $snapshot->aiCostToday);
        self::assertSame('resolve_failed_imports', $snapshot->nextAction);
        self::assertSame('/app/'.$tenant->slug.'/products', $snapshot->nextActionUrl);
        self::assertNotContains('OTHER-ORDER', $snapshot->evidenceIds);
        self::assertSame([], $snapshot->degradedSources);

        $this->expectException(AuthorizationException::class);
        app(OperationalCockpit::class)->snapshot(
            (string) $tenant->id,
            (string) $otherOwner->id,
        );
    }

    /** @return array{Tenant, User, object} */
    private function tenant(string $email): array
    {
        $owner = User::factory()->tenantOwner()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->pro()->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
        ]);
        $location = GlobalMarketFixture::assignCanada($tenant);

        return [$tenant, $owner, $location];
    }
}
