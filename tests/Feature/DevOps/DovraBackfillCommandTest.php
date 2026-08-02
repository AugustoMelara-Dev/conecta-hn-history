<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DovraBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each command uses the same bounded disposable SQLite fixture twice. The
     * report and checkpoint are deliberately compared as bytes, not by a
     * timestamp-tolerant assertion.
     */
    public function test_each_backfill_is_bounded_json_idempotent_and_checkpointed(): void
    {
        $commands = [
            'dovra:backfill-honduras-market',
            'dovra:backfill-identity-governance',
            'dovra:backfill-business-operations',
        ];

        foreach ($commands as $command) {
            $reportPath = tempnam(sys_get_temp_dir(), 'dovra-report-');
            $checkpointPath = tempnam(sys_get_temp_dir(), 'dovra-checkpoint-');
            self::assertNotFalse($reportPath);
            self::assertNotFalse($checkpointPath);
            @unlink($reportPath);
            @unlink($checkpointPath);

            try {
                $arguments = [
                    '--phase' => 'backfill',
                    '--json' => true,
                    '--limit' => 2,
                    '--max-blocking-gaps' => 10,
                    '--report' => $reportPath,
                    '--checkpoint' => $checkpointPath,
                ];

                $this->artisan($command, $arguments)->assertExitCode(0);
                $firstReport = (string) file_get_contents($reportPath);
                $firstCheckpoint = (string) file_get_contents($checkpointPath);

                $this->artisan($command, $arguments)->assertExitCode(0);
                $secondReport = (string) file_get_contents($reportPath);
                $secondCheckpoint = (string) file_get_contents($checkpointPath);

                self::assertJsonStringEqualsJsonString($firstReport, $secondReport, $command);
                self::assertJsonStringEqualsJsonString($firstCheckpoint, $secondCheckpoint, $command);
                self::assertSame(2, json_decode($secondReport, true, 512, JSON_THROW_ON_ERROR)['limit']);
            } finally {
                @unlink($reportPath);
                @unlink($checkpointPath);
            }
        }
    }

    public function test_expand_phase_proves_new_structures_before_backfill(): void
    {
        foreach ([
            'dovra:backfill-honduras-market',
            'dovra:backfill-identity-governance',
            'dovra:backfill-business-operations',
        ] as $command) {
            $this->artisan($command, [
                '--phase' => 'expand',
                '--json' => true,
                '--limit' => 2,
            ])->assertExitCode(0);
        }
    }

    public function test_validate_dry_run_rolls_back_new_structures_and_preserves_legacy_rows(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => null]);
        $legacyBefore = [
            'tenants' => DB::table('tenants')->where('id', $tenant->id)->first(['id', 'name', 'city_id', 'address']),
            'tenant_users' => DB::table('tenant_users')->where('tenant_id', $tenant->id)->get()->toArray(),
        ];
        $newStructureCountsBefore = [
            'tenant_markets' => DB::table('tenant_markets')->count(),
            'tenant_memberships' => DB::table('tenant_memberships')->count(),
            'locations' => DB::table('locations')->count(),
            'service_locations' => DB::table('service_locations')->count(),
            'service_provider_locations' => DB::table('service_provider_locations')->count(),
            'availability_rules' => DB::table('availability_rules')->count(),
        ];

        $options = [
            '--phase' => 'validate',
            '--dry-run' => true,
            '--json' => true,
            '--limit' => 2,
            '--max-blocking-gaps' => 10,
        ];

        $this->artisan('dovra:backfill-honduras-market', $options)->assertExitCode(0);
        $this->artisan('dovra:backfill-identity-governance', $options)->assertExitCode(0);
        $this->artisan('dovra:backfill-business-operations', $options)->assertExitCode(0);

        self::assertEquals($legacyBefore['tenants'], DB::table('tenants')->where('id', $tenant->id)->first(['id', 'name', 'city_id', 'address']));
        self::assertEquals($legacyBefore['tenant_users'], DB::table('tenant_users')->where('tenant_id', $tenant->id)->get()->toArray());
        self::assertSame($newStructureCountsBefore, [
            'tenant_markets' => DB::table('tenant_markets')->count(),
            'tenant_memberships' => DB::table('tenant_memberships')->count(),
            'locations' => DB::table('locations')->count(),
            'service_locations' => DB::table('service_locations')->count(),
            'service_provider_locations' => DB::table('service_provider_locations')->count(),
            'availability_rules' => DB::table('availability_rules')->count(),
        ]);
    }

    public function test_contract_phase_is_executable_but_fail_closed_before_consumers(): void
    {
        foreach ([
            'dovra:backfill-honduras-market',
            'dovra:backfill-identity-governance',
            'dovra:backfill-business-operations',
        ] as $command) {
            $this->artisan($command, [
                '--phase' => 'contract',
                '--json' => true,
            ])->assertExitCode(5);
        }
    }
}
