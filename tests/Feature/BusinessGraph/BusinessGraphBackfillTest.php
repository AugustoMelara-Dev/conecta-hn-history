<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessGraph;

use App\Application\BusinessGraph\BusinessGraphBackfill;
use App\Infrastructure\BusinessGraph\DatabaseBusinessGraphRepository;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BusinessGraphBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_reads_canonical_tables_and_builds_tenant_scoped_graph(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Dovra Merchant']);
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'AI Workforce Starter']);
        $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Workflow Automation']);
        DB::table('opening_hours')->insert([
            'tenant_id' => $tenant->getKey(),
            'day_of_week' => 1,
            'opening_time' => '08:00:00',
            'closing_time' => '17:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('leads')->insert([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Potential Buyer',
            'email' => 'buyer@example.test',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = (new BusinessGraphBackfill(new DatabaseBusinessGraphRepository))->run();

        $this->assertGreaterThanOrEqual(4, $report->factsWritten);
        $this->assertGreaterThanOrEqual(2, $report->edgesWritten);
        $this->assertArrayHasKey('tenant', $report->sourceTypeCounts);
        $this->assertDatabaseHas('dovra_business_facts', [
            'tenant_id' => $tenant->getKey(),
            'subject_type' => 'product',
            'subject_id' => (string) $product->getKey(),
            'fact_key' => 'catalog.name',
            'source_type' => 'product',
            'source_id' => 'product:'.$product->getKey(),
        ]);
        $this->assertDatabaseHas('dovra_business_edges', [
            'tenant_id' => $tenant->getKey(),
            'from_type' => 'tenant',
            'from_id' => (string) $tenant->getKey(),
            'relation' => 'owns',
            'to_type' => 'service',
            'to_id' => (string) $service->getKey(),
        ]);
    }

    public function test_backfill_supports_tenant_filter_dry_run_and_repeated_run_stability(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        Product::factory()->forTenant($tenantA)->create(['name' => 'Tenant A product']);
        Product::factory()->forTenant($tenantB)->create(['name' => 'Tenant B product']);

        $backfill = new BusinessGraphBackfill(new DatabaseBusinessGraphRepository);

        $dryRun = $backfill->run((string) $tenantA->getKey(), dryRun: true);

        $this->assertGreaterThan(0, $dryRun->factsWritten);
        $this->assertSame(0, DB::table('dovra_business_facts')->count());

        $first = $backfill->run((string) $tenantA->getKey());
        $countsAfterFirst = [
            DB::table('dovra_business_facts')->count(),
            DB::table('dovra_business_edges')->count(),
        ];
        $second = $backfill->run((string) $tenantA->getKey());

        $this->assertSame($first->factsWritten, $second->factsWritten);
        $this->assertSame($countsAfterFirst[0], DB::table('dovra_business_facts')->count());
        $this->assertSame($countsAfterFirst[1], DB::table('dovra_business_edges')->count());
        $this->assertDatabaseMissing('dovra_business_facts', [
            'tenant_id' => $tenantB->getKey(),
        ]);
    }
}
