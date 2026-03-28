<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantLegacySnapshotCommandsTest extends TestCase
{
    use DatabaseTransactions;

    private const LEGACY_PAID_PLAN_FLAG = 'is_pro';

    #[Test]
    public function it_audits_legacy_tenant_snapshots_without_counting_non_tenant_snapshots(): void
    {
        $this->insertSnapshot('tenant-needs-normalization', [
            'id' => 'tenant-needs-normalization',
            'name' => 'Tenant Legacy',
            'domain' => 'tenant-legacy',
            'business_type' => 'retail',
            self::LEGACY_PAID_PLAN_FLAG => true,
            'subscription_status' => 'active',
        ]);

        $this->insertSnapshot('tenant-already-normalized', [
            'id' => 'tenant-already-normalized',
            'name' => 'Tenant New',
            'domain' => 'tenant-new',
            'business_type' => 'retail',
            'plan_id' => 'pro',
            'subscription_status' => 'active',
        ]);

        $this->insertSnapshot('user-snapshot', [
            'id' => 'user-snapshot',
            'email' => 'user@example.com',
            self::LEGACY_PAID_PLAN_FLAG => true,
        ]);

        $this->artisan('tenant:snapshots-audit', ['--sample' => 1])
            ->expectsOutput('Snapshot audit complete.')
            ->expectsOutput('Total snapshots: 3')
            ->expectsOutput('Tenant-like snapshots: 2')
            ->expectsOutput('Tenant snapshots with legacy paid-plan flag: 1')
            ->expectsOutput('Tenant snapshots requiring normalization: 1')
            ->expectsOutput('Invalid snapshot payloads: 0')
            ->expectsOutputToContain('Sample aggregate UUIDs requiring normalization: tenant-needs-normalization')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_runs_normalization_in_dry_run_mode_by_default(): void
    {
        $this->insertSnapshot('tenant-dry-run', [
            'id' => 'tenant-dry-run',
            'name' => 'Tenant Dry Run',
            'domain' => 'tenant-dry-run',
            'business_type' => 'retail',
            self::LEGACY_PAID_PLAN_FLAG => true,
            'subscription_status' => 'active',
        ]);

        $this->artisan('tenant:snapshots-normalize-legacy-plan-flag')
            ->expectsOutput('Legacy tenant snapshot normalization complete (dry-run).')
            ->expectsOutput('Snapshots requiring normalization: 1')
            ->expectsOutput('Snapshots normalized: 0')
            ->expectsOutput('Invalid snapshot payloads: 0')
            ->expectsOutputToContain('Re-run with --write to persist normalized snapshots.')
            ->assertExitCode(0);

        $state = json_decode((string) DB::table('snapshots')->where('aggregate_uuid', 'tenant-dry-run')->value('state'), true);

        self::assertIsArray($state);
        self::assertArrayHasKey(self::LEGACY_PAID_PLAN_FLAG, $state);
        self::assertArrayNotHasKey('plan_id', $state);
    }

    #[Test]
    public function it_normalizes_legacy_tenant_snapshots_when_write_mode_is_enabled(): void
    {
        $this->insertSnapshot('tenant-write-mode', [
            'id' => 'tenant-write-mode',
            'name' => 'Tenant Write Mode',
            'domain' => 'tenant-write-mode',
            'business_type' => 'retail',
            self::LEGACY_PAID_PLAN_FLAG => false,
            'subscription_status' => 'inactive',
        ]);

        $this->artisan('tenant:snapshots-normalize-legacy-plan-flag', ['--write' => true])
            ->expectsOutput('Legacy tenant snapshot normalization complete (write).')
            ->expectsOutput('Snapshots requiring normalization: 1')
            ->expectsOutput('Snapshots normalized: 1')
            ->expectsOutput('Invalid snapshot payloads: 0')
            ->assertExitCode(0);

        $state = json_decode((string) DB::table('snapshots')->where('aggregate_uuid', 'tenant-write-mode')->value('state'), true);

        self::assertIsArray($state);
        self::assertSame('semilla', $state['plan_id']);
        self::assertArrayNotHasKey(self::LEGACY_PAID_PLAN_FLAG, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function insertSnapshot(string $aggregateUuid, array $state): void
    {
        DB::table('snapshots')->insert([
            'aggregate_uuid' => $aggregateUuid,
            'aggregate_type' => 'User',
            'aggregate_version' => 1,
            'state' => json_encode($state, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
