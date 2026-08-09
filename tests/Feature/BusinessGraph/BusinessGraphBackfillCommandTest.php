<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessGraph;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BusinessGraphBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_emits_a_report_and_honors_dry_run(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Command Tenant']);

        $this->artisan('dovra:backfill-business-graph', [
            '--tenant' => (string) $tenant->getKey(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('dry_run: true')
            ->expectsOutputToContain('facts_written:')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('dovra_business_facts')->count());
        $this->assertSame(0, DB::table('dovra_business_edges')->count());
    }
}
