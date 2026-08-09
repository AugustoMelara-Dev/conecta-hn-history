<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Application\Crm\TenantIntegrity\CrmTenantIntegrity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmTenantIntegrityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_preflight_is_read_only_deterministic_and_sanitized(): void
    {
        $this->canonicalDealsTable();
        $before = $this->databaseHash();

        $first = app(CrmTenantIntegrity::class)->preflight();
        $second = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('canonical_bigint', $first->shape);
        self::assertSame('READY', $first->status);
        self::assertSame($first->schemaFingerprint, $second->schemaFingerprint);
        self::assertSame($first->dataFingerprint, $second->dataFingerprint);
        self::assertSame($first->toArray(), $second->toArray());
        self::assertFalse($first->writesPerformed);
        self::assertSame($before, $this->databaseHash());
        self::assertStringNotContainsString('Tenant A', json_encode($first, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('@example.test', json_encode($first, JSON_THROW_ON_ERROR));
    }

    public function test_legacy_and_mixed_shapes_are_blocked_without_writes(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('name');
        });
        DB::table('crm_deals')->insert([
            'id' => '00000000-0000-0000-0000-000000000101',
            'tenant_id' => '00000000-0000-0000-0000-000000000001',
            'pipeline_id' => 1,
            'stage_id' => 2,
            'name' => 'Tenant A confidential name',
        ]);
        $legacyHash = $this->databaseHash();

        $legacy = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('legacy_uuid', $legacy->shape);
        self::assertSame('BLOCKED_LEGACY_UUID_CONTRACT', $legacy->status);
        self::assertFalse($legacy->writesPerformed);
        self::assertSame($legacyHash, $this->databaseHash());
        self::assertStringNotContainsString('confidential', json_encode($legacy, JSON_THROW_ON_ERROR));

        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->unsignedBigInteger('crm_pipeline_id')->nullable();
        });
        $mixedHash = $this->databaseHash();

        $mixed = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('mixed_or_unknown', $mixed->shape);
        self::assertSame('BLOCKED_UNKNOWN_SHAPE', $mixed->status);
        self::assertFalse($mixed->writesPerformed);
        self::assertSame($mixedHash, $this->databaseHash());
    }

    public function test_missing_required_table_names_table_and_never_writes(): void
    {
        $this->canonicalDealsTable();
        $before = $this->databaseHash();
        Schema::dropIfExists('crm_contacts');

        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('BLOCKED_MISSING_REQUIRED_TABLE', $report->status);
        self::assertContains('crm_contacts', $report->missingRequiredTables);
        self::assertStringContainsString('crm_contacts', json_encode($report, JSON_THROW_ON_ERROR));
        self::assertFalse($report->writesPerformed);
        self::assertNotSame($before, $this->databaseHash());
        self::assertFalse(Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_cross_tenant_edges_are_counted_with_stable_codes_and_zero_writes(): void
    {
        $this->canonicalDealsTable();
        $tenantA = '00000000-0000-0000-0000-000000000001';
        $tenantB = '00000000-0000-0000-0000-000000000002';
        DB::table('tenants')->updateOrInsert(['id' => $tenantA], ['name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenants')->updateOrInsert(['id' => $tenantB], ['name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_pipelines')->insert([
            ['id' => 990, 'tenant_id' => $tenantA, 'name' => 'A pipeline', 'key' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 991, 'tenant_id' => $tenantB, 'name' => 'B pipeline', 'key' => 'b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_stages')->insert([
            ['id' => 992, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 990, 'name' => 'A stage', 'key' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 994, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 991, 'name' => 'Cross stage', 'key' => 'cross', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_deals')->insert([
            'id' => 993,
            'tenant_id' => $tenantA,
            'crm_pipeline_id' => 991,
            'crm_stage_id' => 992,
            'title' => 'Poisoned edge',
            'value' => 0,
            'currency' => 'HNL',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = $this->databaseHash();

        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('BLOCKED_ANOMALIES', $report->status);
        self::assertSame(1, $report->anomalies['cross_tenant_stage_pipeline']);
        self::assertSame(1, $report->anomalies['cross_tenant_deal_pipeline']);
        self::assertSame(1, $report->anomalies['deal_stage_pipeline_mismatch']);
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $this->databaseHash());
        self::assertFalse(Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_expand_adds_nullable_pivot_provenance_and_parent_candidate_keys_idempotently(): void
    {
        $this->canonicalDealsTable();
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight();

        $expanded = $service->expand($preflight->schemaFingerprint);
        $replayed = $service->expand($expanded->schemaFingerprint);

        self::assertSame('EXPANDED', $expanded->status);
        self::assertTrue($expanded->writesPerformed);
        self::assertTrue(Schema::hasColumn('contact_tag', 'tenant_id'));
        self::assertTrue(Schema::hasColumn('deal_tag', 'tenant_id'));
        self::assertTrue($this->hasIndex('crm_pipelines', 'crm_pipelines_tenant_id_unique'));
        self::assertTrue($this->hasIndex('crm_stages', 'crm_stages_tenant_pipeline_id_unique'));
        self::assertTrue($this->hasIndex('crm_deals', 'crm_deals_tenant_id_unique'));
        self::assertTrue($this->hasIndex('tags', 'tags_tenant_id_unique'));
        self::assertSame('EXPANDED_NOOP', $replayed->status);
        self::assertFalse($replayed->writesPerformed);
    }

    public function test_expand_refuses_stale_fingerprint_without_writes(): void
    {
        $this->canonicalDealsTable();
        $service = app(CrmTenantIntegrity::class);
        $fingerprint = $service->preflight()->schemaFingerprint;
        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->string('preflight_drift_marker')->nullable();
        });
        $before = $this->databaseHash();

        $report = $service->expand($fingerprint);

        self::assertSame('BLOCKED_FINGERPRINT_MISMATCH', $report->status);
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $this->databaseHash());
        self::assertFalse(Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_down_preserves_populated_pivot_provenance(): void
    {
        $this->canonicalDealsTable();
        $service = app(CrmTenantIntegrity::class);
        $expanded = $service->expand();
        self::assertSame('EXPANDED', $expanded->status);
        $tenantId = '00000000-0000-0000-0000-000000000001';
        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Fixture tenant', 'slug' => 'fixture-tenant', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_contacts')->insert(['id' => '00000000-0000-0000-0000-000000000201', 'tenant_id' => $tenantId, 'first_name' => 'Fixture', 'last_name' => 'Contact', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tags')->insert(['id' => '00000000-0000-0000-0000-000000000501', 'tenant_id' => $tenantId, 'name' => 'fixture', 'slug' => 'fixture', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contact_tag')->insert(['contact_id' => '00000000-0000-0000-0000-000000000201', 'tag_id' => '00000000-0000-0000-0000-000000000501', 'tenant_id' => '00000000-0000-0000-0000-000000000001']);
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');
        $migration->down();

        self::assertTrue(Schema::hasColumn('contact_tag', 'tenant_id'));
        self::assertSame(1, DB::table('contact_tag')->whereNotNull('tenant_id')->count());
    }

    private function canonicalDealsTable(): void
    {
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');
        $migration->down();
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->unsignedBigInteger('crm_stage_id');
            $table->unsignedBigInteger('customer_profile_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->uuid('owner_user_id')->nullable();
            $table->uuid('location_id')->nullable();
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('HNL');
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(static fn (array $index): bool => (string) ($index['name'] ?? '') === $name);
    }

    private function databaseHash(): string
    {
        $payload = [];
        foreach (Schema::getTables() as $table) {
            $name = is_object($table) ? (string) ($table->name ?? '') : (string) ($table['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $payload[$name] = [
                'columns' => Schema::getColumns($name),
                'rows' => DB::table($name)->count(),
            ];
        }

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
