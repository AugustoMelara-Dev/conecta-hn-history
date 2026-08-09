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

    public function test_canonical_shape_requires_bigint_id_and_rejects_residual_legacy_vocabulary(): void
    {
        $this->canonicalDealsTable();
        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->string('name')->nullable();
        });

        $residual = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('mixed_or_unknown', $residual->shape);
        self::assertSame('BLOCKED_UNKNOWN_SHAPE', $residual->status);

        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->unsignedBigInteger('crm_stage_id');
            $table->string('title');
        });

        $narrow = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('mixed_or_unknown', $narrow->shape);
        self::assertSame('BLOCKED_UNKNOWN_SHAPE', $narrow->status);
    }

    public function test_mysql_int_unsigned_is_not_accepted_as_canonical_bigint(): void
    {
        $service = app(CrmTenantIntegrity::class);
        $method = new \ReflectionMethod($service, 'isCanonicalBigintId');
        $method->setAccessible(true);

        self::assertFalse($method->invoke($service, 'int unsigned'));
        self::assertTrue($method->invoke($service, 'bigint'));
        self::assertTrue($method->invoke($service, 'bigint unsigned'));
    }

    public function test_expand_requires_non_null_current_fingerprint(): void
    {
        $this->canonicalDealsTable();
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');
        $report = app(CrmTenantIntegrity::class)->expand();

        self::assertSame('BLOCKED_FINGERPRINT_REQUIRED', $report->status);
        self::assertFalse($report->writesPerformed);
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_missing_required_column_blocks_preflight_without_writes(): void
    {
        $this->canonicalDealsTable();
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('crm_contacts');
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('owner_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
        $before = $this->databaseHash();
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');

        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('BLOCKED_REQUIRED_SCHEMA', $report->status);
        self::assertContains('crm_contacts.company_id', $report->missingRequiredColumns);
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $this->databaseHash());
    }

    public function test_public_migration_runner_refuses_legacy_shape_without_writes(): void
    {
        $this->canonicalDealsTable();
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('name');
        });
        $before = $this->databaseHash();
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');

        try {
            $migration->up();
            self::fail('Legacy runner invocation must throw before DDL.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('READY canonical schema fingerprint', $exception->getMessage());
        }

        self::assertSame($before, $this->databaseHash());
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_public_migration_runner_applies_canonical_schema_with_fresh_fingerprint(): void
    {
        $this->canonicalDealsTable();
        $before = $this->databaseHash();
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');

        $migration->up();

        self::assertTrue(Schema::hasColumn('contact_tag', 'tenant_id'));
        self::assertTrue(Schema::hasColumn('deal_tag', 'tenant_id'));
        self::assertNotSame($before, $this->databaseHash());
    }

    public function test_public_migration_runner_refuses_mixed_shape_without_writes(): void
    {
        $this->canonicalDealsTable();
        Schema::table('crm_deals', function (Blueprint $table): void {
            $table->string('stage_id')->nullable();
        });
        $before = $this->databaseHash();
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');

        try {
            $migration->up();
            self::fail('Mixed runner invocation must throw before DDL.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('READY canonical schema fingerprint', $exception->getMessage());
        }

        self::assertSame($before, $this->databaseHash());
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_public_migration_runner_refuses_missing_required_table_without_writes(): void
    {
        $this->canonicalDealsTable();
        Schema::dropIfExists('crm_contacts');
        $before = $this->databaseHash();
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');

        try {
            $migration->up();
            self::fail('Missing-table runner invocation must throw before DDL.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('READY canonical schema fingerprint', $exception->getMessage());
        }

        self::assertSame($before, $this->databaseHash());
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_public_migration_runner_blocks_mysql_without_engine_proof(): void
    {
        $this->canonicalDealsTable();
        $before = $this->databaseHash();
        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');
        $engineGuard = new \ReflectionMethod($migration, 'assertEngineProof');
        $engineGuard->setAccessible(true);

        try {
            $engineGuard->invoke($migration, 'mysql');
            self::fail('MySQL runner invocation must require an external engine proof.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED', $exception->getMessage());
        }

        self::assertSame($before, $this->databaseHash());
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_wrong_traversal_index_blocks_preflight_and_expand_before_ddl(): void
    {
        $this->canonicalDealsTable();
        $this->dropIndexIfPresent('contact_tag', 'contact_tag_tenant_contact_idx');
        if (! Schema::hasColumn('contact_tag', 'tenant_id')) {
            Schema::table('contact_tag', function (Blueprint $table): void {
                $table->uuid('tenant_id')->nullable();
            });
        }
        Schema::table('contact_tag', function (Blueprint $table): void {
            $table->index(['contact_id', 'tenant_id'], 'contact_tag_tenant_contact_idx');
        });
        $before = $this->databaseHash();
        $beforeHasDealTenant = Schema::hasColumn('deal_tag', 'tenant_id');
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight();

        self::assertSame('BLOCKED_ANOMALIES', $preflight->status);
        self::assertSame(1, $preflight->anomalies['incompatible_traversal_index_contact_tag_tenant_contact_idx'] ?? 0);
        $expanded = $service->expand($preflight->schemaFingerprint);

        self::assertSame('BLOCKED_ANOMALIES', $expanded->status);
        self::assertSame($beforeHasDealTenant, Schema::hasColumn('deal_tag', 'tenant_id'));
        self::assertSame($before, $this->databaseHash());
    }

    public function test_report_distinguishes_exact_aggregate_counts_from_bounded_output(): void
    {
        $this->canonicalDealsTable();
        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('exact', $report->details['query_bounds']['aggregate_counts'] ?? null);
        self::assertSame('bounded', $report->details['query_bounds']['output_memory'] ?? null);
        self::assertSame('database-dependent', $report->details['query_bounds']['scan_cost'] ?? null);
        self::assertSame('EXTERNAL_BLOCKED', $report->details['query_bounds']['staging_plan_proof'] ?? null);
    }

    public function test_down_preserves_preexisting_same_name_additions(): void
    {
        $this->canonicalDealsTable();
        Schema::table('contact_tag', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable();
            $table->index(['tenant_id', 'contact_id'], 'contact_tag_tenant_contact_idx');
            $table->index(['tenant_id', 'tag_id'], 'contact_tag_tenant_tag_idx');
        });
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight();
        $expanded = $service->expand($preflight->schemaFingerprint);
        self::assertSame('EXPANDED', $expanded->status);

        $migration = require database_path('migrations/2026_08_22_000001_expand_crm_tenant_integrity.php');
        $migration->down();

        self::assertTrue(Schema::hasColumn('contact_tag', 'tenant_id'));
        self::assertTrue($this->hasIndex('contact_tag', 'contact_tag_tenant_contact_idx'));
        self::assertTrue($this->hasIndex('contact_tag', 'contact_tag_tenant_tag_idx'));
    }

    public function test_missing_required_table_names_table_and_never_writes(): void
    {
        $this->canonicalDealsTable();
        Schema::dropIfExists('crm_contacts');
        $before = $this->databaseHash();
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');

        $report = app(CrmTenantIntegrity::class)->preflight();
        $after = $this->databaseHash();

        self::assertSame('BLOCKED_MISSING_REQUIRED_TABLE', $report->status);
        self::assertContains('crm_contacts', $report->missingRequiredTables);
        self::assertStringContainsString('crm_contacts', json_encode($report, JSON_THROW_ON_ERROR));
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $after);
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
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
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');

        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('BLOCKED_ANOMALIES', $report->status);
        self::assertSame(1, $report->anomalies['cross_tenant_stage_pipeline']);
        self::assertSame(1, $report->anomalies['cross_tenant_deal_pipeline']);
        self::assertSame(1, $report->anomalies['deal_stage_pipeline_mismatch']);
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $this->databaseHash());
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_edge_inventory_covers_nullable_endpoints_notes_tasks_quotes_contacts_companies_and_activity_parents(): void
    {
        $this->canonicalDealsTable();
        $tenantA = '00000000-0000-0000-0000-000000000001';
        $tenantB = '00000000-0000-0000-0000-000000000002';
        $profileA = 7001;
        $profileB = 7002;
        $dealA = 7101;
        $dealB = 7102;
        $companyA = '00000000-0000-0000-0000-000000000301';
        $companyB = '00000000-0000-0000-0000-000000000302';
        $contactA = '00000000-0000-0000-0000-000000000401';
        $contactB = '00000000-0000-0000-0000-000000000402';
        $locationA = '00000000-0000-0000-0000-000000000601';
        $locationB = '00000000-0000-0000-0000-000000000602';

        Schema::disableForeignKeyConstraints();
        DB::table('tenants')->insert([
            ['id' => $tenantA, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $tenantB, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_pipelines')->insert([
            ['id' => 7201, 'tenant_id' => $tenantA, 'name' => 'Pipeline A', 'key' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7202, 'tenant_id' => $tenantB, 'name' => 'Pipeline B', 'key' => 'b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_stages')->insert([
            ['id' => 7301, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 7201, 'name' => 'Stage A', 'key' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7302, 'tenant_id' => $tenantB, 'crm_pipeline_id' => 7202, 'name' => 'Stage B', 'key' => 'b', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7303, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 7202, 'name' => 'Cross stage', 'key' => 'cross', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('customer_profiles')->insert([
            ['id' => $profileA, 'tenant_id' => $tenantA, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $profileB, 'tenant_id' => $tenantB, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('countries')->insert(['code' => 'HN', 'name' => 'Honduras', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('currencies')->insert(['code' => 'HNL', 'name' => 'Lempira', 'symbol' => 'L', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('locales')->insert(['code' => 'es-HN', 'name' => 'Spanish Honduras', 'native_name' => 'Español', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('timezones')->insert(['name' => 'America/Tegucigalpa', 'label' => 'Tegucigalpa', 'created_at' => now(), 'updated_at' => now()]);
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_contacts');
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->string('first_name')->nullable();
            $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('contact_id')->nullable();
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->string('type');
            $table->timestamps();
        });
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('customer_tasks');
        Schema::dropIfExists('customer_notes');
        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('customer_profile_id');
            $table->unsignedBigInteger('crm_deal_id')->nullable();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('customer_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('customer_profile_id');
            $table->unsignedBigInteger('crm_deal_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });
        Schema::create('quotes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('public_id')->unique();
            $table->unsignedBigInteger('crm_deal_id')->nullable();
            $table->char('currency', 3);
            $table->string('idempotency_key');
            $table->char('payload_hash', 64);
            $table->timestamps();
        });
        DB::table('leads')->insert([
            ['id' => 7401, 'tenant_id' => $tenantA, 'name' => 'Lead A', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7402, 'tenant_id' => $tenantB, 'name' => 'Lead B', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('locations')->insert([
            ['id' => $locationA, 'tenant_id' => $tenantA, 'country_code' => 'HN', 'locale_code' => 'es-HN', 'currency_code' => 'HNL', 'timezone_name' => 'America/Tegucigalpa', 'name' => 'Location A', 'slug' => 'location-a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $locationB, 'tenant_id' => $tenantB, 'country_code' => 'HN', 'locale_code' => 'es-HN', 'currency_code' => 'HNL', 'timezone_name' => 'America/Tegucigalpa', 'name' => 'Location B', 'slug' => 'location-b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_deals')->insert([
            ['id' => $dealA, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 7201, 'crm_stage_id' => 7301, 'customer_profile_id' => null, 'lead_id' => null, 'location_id' => null, 'owner_user_id' => null, 'title' => 'Deal A', 'value' => 0, 'currency' => 'HNL', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $dealB, 'tenant_id' => $tenantB, 'crm_pipeline_id' => 7202, 'crm_stage_id' => 7302, 'customer_profile_id' => $profileB, 'lead_id' => 7402, 'location_id' => $locationB, 'owner_user_id' => null, 'title' => 'Deal B', 'value' => 0, 'currency' => 'HNL', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7103, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 7202, 'crm_stage_id' => 7301, 'customer_profile_id' => $profileB, 'lead_id' => 7402, 'location_id' => $locationB, 'owner_user_id' => null, 'title' => 'Cross endpoints', 'value' => 0, 'currency' => 'HNL', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7105, 'tenant_id' => $tenantB, 'crm_pipeline_id' => 7202, 'crm_stage_id' => 7301, 'customer_profile_id' => $profileB, 'lead_id' => 7402, 'location_id' => $locationB, 'owner_user_id' => null, 'title' => 'Cross stage tenant', 'value' => 0, 'currency' => 'HNL', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7104, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 9991, 'crm_stage_id' => 9992, 'customer_profile_id' => 9993, 'lead_id' => 9994, 'location_id' => '00000000-0000-0000-0000-000000009995', 'owner_user_id' => null, 'title' => 'Orphan endpoints', 'value' => 0, 'currency' => 'HNL', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_companies')->insert([
            ['id' => $companyA, 'tenant_id' => $tenantA, 'name' => 'Company A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $companyB, 'tenant_id' => $tenantB, 'name' => 'Company B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_contacts')->insert([
            ['id' => $contactA, 'tenant_id' => $tenantA, 'company_id' => $companyB, 'owner_id' => null, 'first_name' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $contactB, 'tenant_id' => $tenantB, 'company_id' => '00000000-0000-0000-0000-000000009996', 'owner_id' => null, 'first_name' => 'B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_activities')->insert([
            ['id' => '00000000-0000-0000-0000-000000000701', 'tenant_id' => $tenantA, 'contact_id' => $contactB, 'deal_id' => $dealB, 'company_id' => $companyB, 'owner_id' => null, 'type' => 'note', 'created_at' => now(), 'updated_at' => now()],
            ['id' => '00000000-0000-0000-0000-000000000702', 'tenant_id' => $tenantA, 'contact_id' => '00000000-0000-0000-0000-000000009997', 'deal_id' => 9998, 'company_id' => '00000000-0000-0000-0000-000000009999', 'owner_id' => null, 'type' => 'note', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('customer_notes')->insert([
            ['tenant_id' => $tenantA, 'customer_profile_id' => $profileA, 'crm_deal_id' => $dealB, 'body' => 'Cross note', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenantA, 'customer_profile_id' => $profileA, 'crm_deal_id' => 9997, 'body' => 'Orphan note', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('customer_tasks')->insert([
            ['tenant_id' => $tenantA, 'customer_profile_id' => $profileA, 'crm_deal_id' => $dealB, 'title' => 'Cross task', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenantA, 'customer_profile_id' => $profileA, 'crm_deal_id' => 9996, 'title' => 'Orphan task', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('quotes')->insert([
            ['id' => '00000000-0000-0000-0000-000000000801', 'tenant_id' => $tenantA, 'public_id' => 'quote-cross', 'crm_deal_id' => $dealB, 'currency' => 'HNL', 'idempotency_key' => 'quote-cross-key', 'payload_hash' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()],
            ['id' => '00000000-0000-0000-0000-000000000802', 'tenant_id' => $tenantA, 'public_id' => 'quote-orphan', 'crm_deal_id' => 9995, 'currency' => 'HNL', 'idempotency_key' => 'quote-orphan-key', 'payload_hash' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::enableForeignKeyConstraints();

        $before = $this->databaseHash();
        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('BLOCKED_ANOMALIES', $report->status);
        foreach ([
            'cross_tenant_deal_pipeline', 'cross_tenant_deal_stage', 'deal_stage_pipeline_mismatch',
            'orphan_deal_pipeline', 'orphan_deal_stage', 'cross_tenant_deal_lead', 'orphan_deal_lead',
            'cross_tenant_deal_location', 'orphan_deal_location', 'cross_tenant_deal_customer_profile',
            'orphan_deal_customer_profile', 'cross_tenant_customer_note_deal', 'orphan_customer_note_deal',
            'cross_tenant_customer_task_deal', 'orphan_customer_task_deal', 'cross_tenant_quote_deal',
            'orphan_quote_deal', 'cross_tenant_contact_company', 'orphan_contact_company',
            'cross_tenant_activity_contact', 'orphan_activity_contact', 'cross_tenant_activity_deal',
            'orphan_activity_deal', 'cross_tenant_activity_company', 'orphan_activity_company',
        ] as $code) {
            self::assertGreaterThan(0, $report->anomalies[$code] ?? 0, $code);
        }
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $this->databaseHash());
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

    public function test_expanded_pivots_report_null_provenance_mismatch_and_orphan_endpoints(): void
    {
        $this->canonicalDealsTable();
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight();
        $expanded = $service->expand($preflight->schemaFingerprint);
        self::assertSame('EXPANDED', $expanded->status);

        $tenantA = '00000000-0000-0000-0000-000000000001';
        $tenantB = '00000000-0000-0000-0000-000000000002';
        $contactA = '00000000-0000-0000-0000-000000001001';
        $contactB = '00000000-0000-0000-0000-000000001002';
        $tagA = '00000000-0000-0000-0000-000000002001';
        $tagB = '00000000-0000-0000-0000-000000002002';
        Schema::disableForeignKeyConstraints();
        DB::table('tenants')->insert([
            ['id' => $tenantA, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $tenantB, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_contacts')->insert([
            ['id' => $contactA, 'tenant_id' => $tenantA, 'company_id' => null, 'owner_id' => null, 'first_name' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $contactB, 'tenant_id' => $tenantB, 'company_id' => null, 'owner_id' => null, 'first_name' => 'B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('tags')->insert([
            ['id' => $tagA, 'tenant_id' => $tenantA, 'name' => 'A', 'slug' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $tagB, 'tenant_id' => $tenantB, 'name' => 'B', 'slug' => 'b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_deals')->insert([
            ['id' => 8101, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 1, 'crm_stage_id' => 1, 'customer_profile_id' => null, 'lead_id' => null, 'location_id' => null, 'owner_user_id' => null, 'title' => 'A', 'value' => 0, 'currency' => 'HNL', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8102, 'tenant_id' => $tenantB, 'crm_pipeline_id' => 1, 'crm_stage_id' => 1, 'customer_profile_id' => null, 'lead_id' => null, 'location_id' => null, 'owner_user_id' => null, 'title' => 'B', 'value' => 0, 'currency' => 'HNL', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('deal_tag');
        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->uuid('contact_id');
            $table->uuid('tag_id');
            $table->uuid('tenant_id')->nullable();
            $table->timestamps();
        });
        Schema::create('deal_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('deal_id');
            $table->uuid('tag_id');
            $table->uuid('tenant_id')->nullable();
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
        DB::table('contact_tag')->insert([
            ['contact_id' => $contactA, 'tag_id' => $tagB, 'tenant_id' => null],
            ['contact_id' => $contactA, 'tag_id' => $tagA, 'tenant_id' => $tenantB],
            ['contact_id' => $contactA, 'tag_id' => $tagA, 'tenant_id' => $tenantA],
            ['contact_id' => '00000000-0000-0000-0000-000000009001', 'tag_id' => $tagA, 'tenant_id' => $tenantA],
            ['contact_id' => $contactA, 'tag_id' => '00000000-0000-0000-0000-000000009002', 'tenant_id' => $tenantA],
        ]);
        DB::table('deal_tag')->insert([
            ['deal_id' => 8101, 'tag_id' => $tagB, 'tenant_id' => null],
            ['deal_id' => 8101, 'tag_id' => $tagA, 'tenant_id' => $tenantB],
            ['deal_id' => 8101, 'tag_id' => $tagA, 'tenant_id' => $tenantA],
            ['deal_id' => 8991, 'tag_id' => $tagA, 'tenant_id' => $tenantA],
            ['deal_id' => 8101, 'tag_id' => '00000000-0000-0000-0000-000000009003', 'tenant_id' => $tenantA],
        ]);

        $report = $service->preflight();
        foreach ([
            'null_pivot_tenant_contact_tag', 'cross_tenant_contact_tag', 'pivot_tenant_left_mismatch_contact_tag',
            'pivot_tenant_right_mismatch_contact_tag', 'orphan_contact_tag_contact_id', 'orphan_contact_tag_tag_id',
            'null_pivot_tenant_deal_tag', 'cross_tenant_deal_tag', 'pivot_tenant_left_mismatch_deal_tag',
            'pivot_tenant_right_mismatch_deal_tag', 'orphan_deal_tag_deal_id', 'orphan_deal_tag_tag_id',
        ] as $code) {
            self::assertGreaterThan(0, $report->anomalies[$code] ?? 0, $code);
        }
        self::assertFalse($report->writesPerformed);
    }

    public function test_owner_lifecycle_anomalies_and_duplicate_memberships_are_aggregate_and_sanitized(): void
    {
        $this->canonicalDealsTable();
        $tenantA = '00000000-0000-0000-0000-000000000001';
        $tenantB = '00000000-0000-0000-0000-000000000002';
        $userValid = '00000000-0000-0000-0000-000000003001';
        $userPending = '00000000-0000-0000-0000-000000003002';
        $userInactive = '00000000-0000-0000-0000-000000003003';
        $userSuspended = '00000000-0000-0000-0000-000000003004';
        $userRevoked = '00000000-0000-0000-0000-000000003005';
        $userAmbiguous = '00000000-0000-0000-0000-000000003006';
        $userNone = '00000000-0000-0000-0000-000000003007';

        Schema::disableForeignKeyConstraints();
        DB::table('tenants')->insert([
            ['id' => $tenantA, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $tenantB, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([$userValid, $userPending, $userInactive, $userSuspended, $userRevoked, $userAmbiguous, $userNone] as $index => $userId) {
            DB::table('users')->insert([
                'id' => $userId,
                'name' => 'Fixture user '.(string) $index,
                'email' => 'owner-'.$index.'@example.test',
                'password' => 'hash',
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Schema::dropIfExists('tenant_memberships');
        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('status')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });
        DB::table('tenant_memberships')->insert([
            ['id' => '00000000-0000-0000-0000-000000004001', 'tenant_id' => $tenantA, 'user_id' => $userValid, 'status' => 'active', 'accepted_at' => now(), 'suspended_at' => null, 'revoked_at' => null],
            ['id' => '00000000-0000-0000-0000-000000004002', 'tenant_id' => $tenantA, 'user_id' => $userPending, 'status' => 'active', 'accepted_at' => null, 'suspended_at' => null, 'revoked_at' => null],
            ['id' => '00000000-0000-0000-0000-000000004003', 'tenant_id' => $tenantA, 'user_id' => $userInactive, 'status' => 'inactive', 'accepted_at' => now(), 'suspended_at' => null, 'revoked_at' => null],
            ['id' => '00000000-0000-0000-0000-000000004004', 'tenant_id' => $tenantA, 'user_id' => $userSuspended, 'status' => 'active', 'accepted_at' => now(), 'suspended_at' => now(), 'revoked_at' => null],
            ['id' => '00000000-0000-0000-0000-000000004005', 'tenant_id' => $tenantA, 'user_id' => $userRevoked, 'status' => 'active', 'accepted_at' => now(), 'suspended_at' => null, 'revoked_at' => now()],
            ['id' => '00000000-0000-0000-0000-000000004006', 'tenant_id' => $tenantA, 'user_id' => $userAmbiguous, 'status' => 'active', 'accepted_at' => now(), 'suspended_at' => null, 'revoked_at' => null],
            ['id' => '00000000-0000-0000-0000-000000004007', 'tenant_id' => $tenantA, 'user_id' => $userAmbiguous, 'status' => 'active', 'accepted_at' => now(), 'suspended_at' => null, 'revoked_at' => null],
        ]);
        $owners = [$userValid, $userPending, $userInactive, $userSuspended, $userRevoked, $userAmbiguous, $userNone];
        foreach ($owners as $offset => $ownerId) {
            DB::table('crm_deals')->insert([
                'id' => 8300 + $offset,
                'tenant_id' => $tenantA,
                'crm_pipeline_id' => 1,
                'crm_stage_id' => 1,
                'customer_profile_id' => null,
                'lead_id' => null,
                'location_id' => null,
                'owner_user_id' => $ownerId,
                'title' => 'Owner fixture '.(string) $offset,
                'value' => 0,
                'currency' => 'HNL',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Schema::enableForeignKeyConstraints();

        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('BLOCKED_ANOMALIES', $report->status);
        foreach (['owner_without_membership', 'owner_ambiguous_membership', 'owner_inactive_membership', 'owner_pending_membership', 'owner_suspended_membership', 'owner_revoked_membership', 'duplicate_parent_candidate_tenant_memberships'] as $code) {
            self::assertGreaterThan(0, $report->anomalies[$code] ?? 0, $code);
        }
        self::assertStringNotContainsString('owner-', json_encode($report, JSON_THROW_ON_ERROR));
        self::assertFalse($report->writesPerformed);
    }

    public function test_unexpected_id_types_and_incompatible_named_constraints_fail_closed(): void
    {
        $this->canonicalDealsTable();
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('deal_tag');
        Schema::dropIfExists('tags');
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug');
        });
        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->uuid('contact_id');
            $table->unsignedBigInteger('tag_id');
        });
        Schema::create('deal_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('deal_id');
            $table->unsignedBigInteger('tag_id');
        });
        Schema::enableForeignKeyConstraints();
        Schema::table('crm_pipelines', function (Blueprint $table): void {
            $table->index(['tenant_id', 'name'], 'crm_pipelines_tenant_id_unique');
        });

        $report = app(CrmTenantIntegrity::class)->preflight();

        self::assertSame('BLOCKED_ANOMALIES', $report->status);
        self::assertSame(1, $report->anomalies['unexpected_id_type_tags.id'] ?? 0);
        self::assertSame(1, $report->anomalies['incompatible_existing_constraint_crm_pipelines_tenant_id_unique'] ?? 0);
        self::assertFalse($report->writesPerformed);
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
        $beforeHasContactTenant = Schema::hasColumn('contact_tag', 'tenant_id');

        $report = $service->expand($fingerprint);

        self::assertSame('BLOCKED_FINGERPRINT_MISMATCH', $report->status);
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $this->databaseHash());
        self::assertSame($beforeHasContactTenant, Schema::hasColumn('contact_tag', 'tenant_id'));
    }

    public function test_down_preserves_populated_pivot_provenance(): void
    {
        $this->canonicalDealsTable();
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight();
        $expanded = $service->expand($preflight->schemaFingerprint);
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

    public function test_backfill_derives_only_identical_endpoint_tenants_for_both_pivots(): void
    {
        $this->prepareBackfillFixture();
        $service = app(CrmTenantIntegrity::class);

        $report = $service->backfill(limit: 500);

        self::assertSame('BACKFILLED', $report->status);
        self::assertTrue($report->writesPerformed);
        self::assertSame(2, $report->details['counts']['updated'] ?? null);
        self::assertSame('00000000-0000-0000-0000-000000000001', DB::table('contact_tag')->value('tenant_id'));
        self::assertSame('00000000-0000-0000-0000-000000000001', DB::table('deal_tag')->value('tenant_id'));
    }

    public function test_backfill_blocks_mismatch_orphan_and_duplicate_pivots_without_writes(): void
    {
        $fixture = $this->prepareBackfillFixture(includeValidRows: false, contactRows: 2);
        DB::table('tags')->where('id', $fixture['tag_b'])->update(['tenant_id' => $fixture['tenant_b']]);
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('deal_tag');
        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->uuid('contact_id');
            $table->uuid('tag_id');
            $table->uuid('tenant_id')->nullable();
        });
        Schema::create('deal_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('deal_id');
            $table->uuid('tag_id');
            $table->uuid('tenant_id')->nullable();
        });
        Schema::enableForeignKeyConstraints();
        DB::table('contact_tag')->insert([
            ['contact_id' => $fixture['contact_a'], 'tag_id' => $fixture['tag_b'], 'tenant_id' => null],
            ['contact_id' => $fixture['contact_a'], 'tag_id' => $fixture['tag_a'], 'tenant_id' => null],
            ['contact_id' => $fixture['contact_a'], 'tag_id' => $fixture['tag_a'], 'tenant_id' => null],
        ]);
        DB::table('deal_tag')->insert([
            ['deal_id' => $fixture['deal_a'], 'tag_id' => $fixture['tag_b'], 'tenant_id' => null],
            ['deal_id' => $fixture['deal_a'], 'tag_id' => '00000000-0000-0000-0000-000000009999', 'tenant_id' => null],
        ]);
        $before = $this->databaseHash();

        $report = app(CrmTenantIntegrity::class)->backfill(limit: 500);

        self::assertSame('BLOCKED_ANOMALIES', $report->status);
        self::assertFalse($report->writesPerformed);
        self::assertSame($before, $this->databaseHash());
        self::assertGreaterThan(0, $report->anomalies['cross_tenant_contact_tag'] ?? 0);
        self::assertGreaterThan(0, $report->anomalies['orphan_deal_tag_tag_id'] ?? 0);
        self::assertGreaterThan(0, $report->anomalies['duplicate_pivot_contact_tag'] ?? 0);
    }

    public function test_backfill_resume_is_bounded_and_replay_is_idempotent(): void
    {
        $fixture = $this->prepareBackfillFixture(includeValidRows: false, contactRows: 3, dealRows: 0);
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-');
        self::assertNotFalse($checkpoint);
        @unlink($checkpoint);
        for ($i = 0; $i < 3; $i++) {
            DB::table('contact_tag')->insert([
                'contact_id' => $fixture['contacts'][$i],
                'tag_id' => $fixture['tags'][$i],
                'tenant_id' => null,
            ]);
        }

        $first = app(CrmTenantIntegrity::class)->backfill(limit: 2, checkpoint: $checkpoint);
        self::assertSame('BACKFILLED', $first->status);
        self::assertSame(2, $first->details['counts']['updated'] ?? null);
        self::assertSame(1, DB::table('contact_tag')->whereNull('tenant_id')->count());

        $second = app(CrmTenantIntegrity::class)->backfill(limit: 2, checkpoint: $checkpoint, resume: true);
        self::assertSame('BACKFILLED', $second->status);
        self::assertSame(1, $second->details['counts']['updated'] ?? null);
        self::assertSame(0, DB::table('contact_tag')->whereNull('tenant_id')->count());

        $replay = app(CrmTenantIntegrity::class)->backfill(limit: 2, checkpoint: $checkpoint, resume: true);
        self::assertSame('BACKFILLED_NOOP', $replay->status);
        self::assertSame(0, $replay->details['counts']['updated'] ?? null);
        self::assertSame(3, DB::table('contact_tag')->whereNotNull('tenant_id')->count());
        @unlink($checkpoint);
    }

    public function test_backfill_dry_run_rolls_back_rows_and_checkpoint(): void
    {
        $this->prepareBackfillFixture();
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-dry-');
        self::assertNotFalse($checkpoint);
        $sentinel = '{"untouched":true}';
        file_put_contents($checkpoint, $sentinel);

        $report = app(CrmTenantIntegrity::class)->backfill(limit: 500, checkpoint: $checkpoint, dryRun: true);

        self::assertSame('BACKFILL_DRY_RUN', $report->status);
        self::assertFalse($report->writesPerformed);
        self::assertSame(2, $report->details['counts']['updated'] ?? null);
        self::assertSame(1, DB::table('contact_tag')->whereNull('tenant_id')->count());
        self::assertSame(1, DB::table('deal_tag')->whereNull('tenant_id')->count());
        self::assertSame($sentinel, file_get_contents($checkpoint));
        @unlink($checkpoint);
    }

    public function test_backfill_rejects_tampered_checkpoint_and_source_drift_without_writes(): void
    {
        $fixture = $this->prepareBackfillFixture();
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-drift-');
        self::assertNotFalse($checkpoint);
        @unlink($checkpoint);

        $first = app(CrmTenantIntegrity::class)->backfill(limit: 1, checkpoint: $checkpoint);
        self::assertSame('BACKFILLED', $first->status);
        $original = (string) file_get_contents($checkpoint);
        $tampered = json_decode($original, true, 512, JSON_THROW_ON_ERROR);
        $tampered['sequence'] = 999;
        file_put_contents($checkpoint, json_encode($tampered, JSON_THROW_ON_ERROR));

        $invalid = app(CrmTenantIntegrity::class)->backfill(limit: 1, checkpoint: $checkpoint, resume: true);
        self::assertSame('BLOCKED_CHECKPOINT', $invalid->status);
        self::assertSame(1, DB::table('contact_tag')->whereNotNull('tenant_id')->count());

        file_put_contents($checkpoint, $original);
        DB::table('crm_contacts')->where('id', $fixture['contact_a'])->update(['tenant_id' => $fixture['tenant_b']]);
        $drift = app(CrmTenantIntegrity::class)->backfill(limit: 1, checkpoint: $checkpoint, resume: true);
        self::assertSame('BLOCKED_SOURCE_DRIFT', $drift->status);
        self::assertSame(1, DB::table('contact_tag')->whereNotNull('tenant_id')->count());
        @unlink($checkpoint);
    }

    public function test_backfill_resume_rejects_external_null_to_valid_provenance_change(): void
    {
        $fixture = $this->prepareBackfillFixture(includeValidRows: false, contactRows: 3, dealRows: 0);
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-external-fill-');
        self::assertNotFalse($checkpoint);
        @unlink($checkpoint);
        for ($i = 0; $i < 3; $i++) {
            DB::table('contact_tag')->insert([
                'contact_id' => $fixture['contacts'][$i],
                'tag_id' => $fixture['tags'][$i],
                'tenant_id' => null,
            ]);
        }

        $first = app(CrmTenantIntegrity::class)->backfill(limit: 1, checkpoint: $checkpoint);
        self::assertSame('BACKFILLED', $first->status);
        $checkpointBeforeResume = (string) file_get_contents($checkpoint);
        DB::table('contact_tag')
            ->where('contact_id', $fixture['contacts'][1])
            ->where('tag_id', $fixture['tags'][1])
            ->update(['tenant_id' => $fixture['tenant_a']]);

        $resume = app(CrmTenantIntegrity::class)->backfill(limit: 1, checkpoint: $checkpoint, resume: true);

        self::assertSame('BLOCKED_SOURCE_DRIFT', $resume->status);
        self::assertSame(2, DB::table('contact_tag')->whereNotNull('tenant_id')->count());
        self::assertSame(1, DB::table('contact_tag')->whereNull('tenant_id')->count());
        self::assertSame($checkpointBeforeResume, file_get_contents($checkpoint));
        @unlink($checkpoint);
    }

    public function test_backfill_rechecks_anomalies_inserted_after_outer_gate_before_any_cas(): void
    {
        $fixture = $this->prepareBackfillFixture(includeValidRows: false, contactRows: 2, dealRows: 0);
        DB::table('tags')->where('id', $fixture['tag_b'])->update(['tenant_id' => $fixture['tenant_b']]);
        DB::table('contact_tag')->insert([
            'contact_id' => $fixture['contact_a'],
            'tag_id' => $fixture['tag_a'],
            'tenant_id' => null,
        ]);

        $connection = DB::connection();
        $connection->enableQueryLog();
        app(CrmTenantIntegrity::class)->preflight('backfill');
        $queries = $connection->getQueryLog();
        $connection->disableQueryLog();
        self::assertNotEmpty($queries);
        $marker = (string) $queries[array_key_last($queries)]['query'];
        $seen = 0;
        $inserted = false;
        $markerInsideWriteTransaction = false;
        $pdo = $connection->getPdo();
        DB::listen(function (object $query) use (&$seen, &$inserted, &$markerInsideWriteTransaction, $marker, $fixture, $pdo): void {
            if ($inserted || (string) $query->sql !== $marker) {
                return;
            }
            $markerInsideWriteTransaction = $pdo->inTransaction();
            $inserted = true;
            DB::table('contact_tag')->insert([
                'contact_id' => $fixture['contacts'][1],
                'tag_id' => $fixture['tag_b'],
                'tenant_id' => null,
            ]);
            $seen++;
        });

        $report = app(CrmTenantIntegrity::class)->backfill(limit: 1);

        self::assertTrue($inserted);
        self::assertTrue($markerInsideWriteTransaction);
        self::assertSame(1, $seen);
        self::assertSame('BLOCKED_ANOMALIES', $report->status);
        self::assertFalse($report->writesPerformed);
        self::assertSame(0, DB::table('contact_tag')->whereNotNull('tenant_id')->count());
    }

    public function test_backfill_rolls_back_when_selected_endpoint_changes_before_cas(): void
    {
        $fixture = $this->prepareBackfillFixture(contactRows: 1, dealRows: 0);
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-endpoint-race-');
        self::assertNotFalse($checkpoint);
        $sentinel = '{"untouched":true}';
        file_put_contents($checkpoint, $sentinel);

        $selectionSeen = false;
        $casEndpointReadSeen = false;
        $endpointChanged = false;
        DB::listen(function (object $query) use (&$selectionSeen, &$casEndpointReadSeen, &$endpointChanged, $fixture): void {
            $sql = (string) $query->sql;
            if (! $selectionSeen
                && str_contains($sql, 'from "contact_tag" as "pivot"')
                && str_contains($sql, 'where "pivot"."tenant_id" is null')
                && str_contains($sql, 'limit 1')) {
                $selectionSeen = true;

                return;
            }
            if (! $selectionSeen || $endpointChanged
                || ! str_contains($sql, 'from "crm_contacts"')
                || ! str_contains($sql, 'where "id" = ?')
                || ! str_contains($sql, 'limit 1')) {
                return;
            }
            $casEndpointReadSeen = true;
            DB::table('tags')
                ->where('id', $fixture['tag_a'])
                ->update(['tenant_id' => $fixture['tenant_b']]);
            $endpointChanged = true;
        });

        $report = app(CrmTenantIntegrity::class)->backfill(limit: 1, checkpoint: $checkpoint);

        self::assertTrue($selectionSeen);
        self::assertTrue($casEndpointReadSeen);
        self::assertTrue($endpointChanged);
        self::assertSame('BLOCKED_SOURCE_DRIFT', $report->status);
        self::assertContains('endpoint_changed_during_batch', $report->blockingIssues);
        self::assertFalse($report->writesPerformed);
        self::assertSame($fixture['tenant_a'], DB::table('crm_contacts')->where('id', $fixture['contact_a'])->value('tenant_id'));
        self::assertSame($fixture['tenant_a'], DB::table('tags')->where('id', $fixture['tag_a'])->value('tenant_id'));
        self::assertSame(1, DB::table('contact_tag')->whereNull('tenant_id')->count());
        self::assertSame($sentinel, file_get_contents($checkpoint));
        @unlink($checkpoint);
    }

    public function test_backfill_fails_closed_for_non_testing_outer_transaction(): void
    {
        $this->prepareBackfillFixture(contactRows: 1, dealRows: 0);
        $application = app();
        $environment = (string) $application->environment();
        $application->instance('env', 'production');

        try {
            $report = app(CrmTenantIntegrity::class)->backfill(limit: 1);
        } finally {
            $application->instance('env', $environment);
        }

        self::assertSame('BLOCKED_METADATA_FAILURE', $report->status);
        self::assertContains('sqlite_outer_transaction_active', $report->blockingIssues);
        self::assertFalse($report->writesPerformed);
        self::assertSame(1, DB::table('contact_tag')->whereNull('tenant_id')->count());
    }

    public function test_backfill_source_fingerprint_uses_streaming_row_queries(): void
    {
        $source = file_get_contents(app_path('Application/Crm/TenantIntegrity/CrmTenantIntegrity.php'));
        self::assertIsString($source);
        self::assertGreaterThanOrEqual(2, substr_count($source, '->cursor()'));
        self::assertStringNotContainsString('->get()->map', $source);
    }

    /** @return array<string, mixed> */
    private function prepareBackfillFixture(bool $includeValidRows = true, int $contactRows = 1, int $dealRows = 1): array
    {
        $this->canonicalDealsTable();
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight();
        $expanded = $service->expand($preflight->schemaFingerprint);
        self::assertSame('EXPANDED', $expanded->status);

        $tenantA = '00000000-0000-0000-0000-000000000001';
        $tenantB = '00000000-0000-0000-0000-000000000002';
        DB::table('tenants')->insert([
            ['id' => $tenantA, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $tenantB, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_pipelines')->insert([
            ['id' => 1, 'tenant_id' => $tenantA, 'name' => 'Pipeline A', 'key' => 'pipeline-a', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_stages')->insert([
            ['id' => 1, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 1, 'name' => 'Stage A', 'key' => 'stage-a', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $contacts = [];
        $tags = [];
        for ($i = 0; $i < max($contactRows, $dealRows, 1); $i++) {
            $contact = sprintf('00000000-0000-0000-0000-000000003%03d', $i + 1);
            $tag = sprintf('00000000-0000-0000-0000-000000004%03d', $i + 1);
            DB::table('crm_contacts')->insert([
                'id' => $contact,
                'tenant_id' => $tenantA,
                'company_id' => null,
                'owner_id' => null,
                'first_name' => 'Fixture',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('tags')->insert([
                'id' => $tag,
                'tenant_id' => $tenantA,
                'name' => 'Fixture',
                'slug' => 'fixture-'.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $contacts[] = $contact;
            $tags[] = $tag;
        }
        $dealIds = [];
        for ($i = 0; $i < $dealRows; $i++) {
            $deal = 9800 + $i;
            DB::table('crm_deals')->insert([
                'id' => $deal,
                'tenant_id' => $tenantA,
                'crm_pipeline_id' => 1,
                'crm_stage_id' => 1,
                'customer_profile_id' => null,
                'lead_id' => null,
                'location_id' => null,
                'owner_user_id' => null,
                'title' => 'Fixture deal',
                'value' => 0,
                'currency' => 'HNL',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $dealIds[] = $deal;
        }
        if ($includeValidRows) {
            DB::table('contact_tag')->insert(['contact_id' => $contacts[0], 'tag_id' => $tags[0], 'tenant_id' => null]);
            if ($dealIds !== []) {
                DB::table('deal_tag')->insert(['deal_id' => $dealIds[0], 'tag_id' => $tags[0], 'tenant_id' => null]);
            }
        }

        return [
            'tenant_a' => $tenantA,
            'tenant_b' => $tenantB,
            'contact_a' => $contacts[0],
            'contacts' => $contacts,
            'tags' => $tags,
            'tag_a' => $tags[0],
            'tag_b' => $tags[1] ?? $tags[0],
            'deal_a' => $dealIds[0] ?? 9800,
        ];
    }

    private function canonicalDealsTable(): void
    {
        $this->resetExpansionArtifacts();
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

    private function resetExpansionArtifacts(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            ['contact_tag', ['contact_tag_tenant_contact_idx', 'contact_tag_tenant_tag_idx']],
            ['deal_tag', ['deal_tag_tenant_deal_idx', 'deal_tag_tenant_tag_idx']],
        ] as [$table, $indexes]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $index) {
                $this->dropIndexIfPresent($table, $index);
            }
            if (Schema::hasColumn($table, 'tenant_id')
                && ! DB::table($table)->whereNotNull('tenant_id')->exists()) {
                Schema::table($table, static function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('tenant_id');
                });
            }
        }

        foreach ([
            'crm_pipelines_tenant_id_unique', 'crm_stages_tenant_pipeline_id_unique',
            'crm_deals_tenant_id_unique', 'crm_companies_tenant_id_unique',
            'crm_contacts_tenant_id_unique', 'tags_tenant_id_unique',
            'leads_tenant_id_unique', 'locations_tenant_id_unique',
            'customer_profiles_tenant_id_unique', 'customer_notes_tenant_id_unique',
            'customer_tasks_tenant_id_unique', 'quotes_tenant_id_unique',
            'tenant_memberships_tenant_user_id_unique',
        ] as $index) {
            foreach (Schema::getTables() as $tableDefinition) {
                $table = is_object($tableDefinition)
                    ? (string) ($tableDefinition->name ?? '')
                    : (string) ($tableDefinition['name'] ?? '');
                if ($table === '' || ! $this->hasIndex($table, $index) || DB::table($table)->exists()) {
                    continue;
                }
                Schema::table($table, static function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropUnique($index);
                });
            }
        }
        Schema::enableForeignKeyConstraints();
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(static fn (array $index): bool => (string) ($index['name'] ?? '') === $name);
    }

    private function dropIndexIfPresent(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }

    private function databaseHash(): string
    {
        $payload = [];
        $tables = [];
        foreach (Schema::getTables() as $table) {
            $name = is_object($table) ? (string) ($table->name ?? '') : (string) ($table['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $tables[] = $name;
        }
        sort($tables);
        foreach ($tables as $name) {
            $columns = array_map(static fn (array $column): array => [
                'name' => (string) ($column['name'] ?? ''),
                'type' => strtolower((string) ($column['type_name'] ?? $column['type'] ?? '')),
                'nullable' => (bool) ($column['nullable'] ?? false),
                'default' => $column['default'] ?? null,
                'auto_increment' => (bool) ($column['auto_increment'] ?? false),
            ], Schema::getColumns($name));
            usort($columns, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));
            $indexes = array_map(static fn (array $index): array => [
                'name' => (string) ($index['name'] ?? ''),
                'columns' => array_values(array_map('strval', is_array($index['columns'] ?? null) ? $index['columns'] : [])),
                'unique' => (bool) ($index['unique'] ?? false),
                'primary' => (bool) ($index['primary'] ?? false),
            ], Schema::getIndexes($name));
            usort($indexes, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));
            $foreignKeys = Schema::getForeignKeys($name);
            usort($foreignKeys, static fn (array $left, array $right): int => strcmp(json_encode($left), json_encode($right)));
            $rows = DB::table($name)->get()->map(static function (object $row): array {
                $values = (array) $row;
                ksort($values);

                return $values;
            })->all();
            usort($rows, static fn (array $left, array $right): int => strcmp(json_encode($left), json_encode($right)));
            $payload[$name] = [
                'columns' => $columns,
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
                'rows' => $rows,
            ];
        }

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
