<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Application\Crm\LegacyUuid\CrmLegacyUuidReconciliation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmLegacyUuidReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_is_deterministically_blocked_without_writes(): void
    {
        $this->setUpMinimalCanonicalShape();
        $before = Schema::getColumnListing('crm_deals');
        $beforeTables = array_map(static fn (mixed $table): string => is_object($table) ? (string) ($table->name ?? '') : (string) ($table['name'] ?? ''), Schema::getTables());

        $report = app(CrmLegacyUuidReconciliation::class)->contract();

        self::assertSame('blocked', $report->status);
        self::assertSame('contract', $report->phase);
        self::assertSame($before, Schema::getColumnListing('crm_deals'));
        $afterTables = array_map(static fn (mixed $table): string => is_object($table) ? (string) ($table->name ?? '') : (string) ($table['name'] ?? ''), Schema::getTables());
        self::assertSame($beforeTables, $afterTables);
        self::assertSame([], DB::table('crm_deals')->get()->all());
    }

    public function test_canonical_shape_is_a_strict_expand_and_backfill_noop(): void
    {
        $this->setUpMinimalCanonicalShape();
        $beforeTables = Schema::getTables();

        $service = app(CrmLegacyUuidReconciliation::class);
        self::assertSame('canonical_bigint', $service->shape());
        self::assertSame('canonical_noop', $service->expand()->status);
        self::assertSame('canonical_noop', $service->backfill()->status);
        self::assertSame('canonical_noop', $service->validate()->status);
        self::assertSame($beforeTables, Schema::getTables());
    }

    public function test_exact_ac66f32f_fixture_expands_backfills_children_and_replays_idempotently(): void
    {
        $this->setUpLegacyFixture();
        $service = app(CrmLegacyUuidReconciliation::class);

        self::assertSame('legacy_uuid', $service->shape());
        self::assertSame('legacy_ready', $service->expand()->status);
        self::assertSame(0, DB::table('crm_deals_canonical_shadow')->count());

        $dry = $service->backfill(limit: 1, dryRun: true);
        self::assertSame('legacy_ready', $dry->status);
        self::assertTrue($dry->dryRun);
        self::assertSame(0, DB::table('crm_deals_canonical_shadow')->count());
        self::assertSame(0, DB::table('crm_deal_legacy_id_map')->count());

        $first = $service->backfill(limit: 1);
        self::assertSame('legacy_ready', $first->status);
        self::assertSame(1, $first->mappedRows);
        self::assertDatabaseCount('crm_deals_canonical_shadow', 1);
        self::assertDatabaseCount('crm_deal_legacy_id_map', 1);
        self::assertDatabaseCount('crm_deal_legacy_payloads', 1);
        self::assertDatabaseHas('crm_deals_canonical_shadow', [
            'legacy_uuid' => '00000000-0000-0000-0000-000000000101',
            'tenant_id' => '00000000-0000-0000-0000-000000000001',
            'crm_pipeline_id' => 7,
            'crm_stage_id' => 9,
            'title' => 'Legacy opportunity',
            'value' => '1250.00',
            'currency' => 'HNL',
            'status' => 'open',
            'source' => 'legacy_pr391',
        ]);
        self::assertNotNull(DB::table('crm_activities')->value('canonical_deal_id'));
        self::assertNotNull(DB::table('deal_tag')->value('canonical_deal_id'));

        $second = $service->backfill(limit: 1);
        self::assertSame('legacy_ready', $second->status);
        self::assertSame(1, $second->mappedRows);
        self::assertSame(1, DB::table('crm_deals_canonical_shadow')->count());
        $validation = $service->validate();
        self::assertSame('legacy_ready', $validation->status);
    }

    public function test_source_checksum_drift_is_blocked_without_overwrite(): void
    {
        $this->setUpLegacyFixture();
        $service = app(CrmLegacyUuidReconciliation::class);
        $service->expand();
        $service->backfill();
        DB::table('crm_deals')->where('id', '00000000-0000-0000-0000-000000000101')->update(['name' => 'Changed after map']);

        $report = $service->backfill();
        self::assertSame('blocked', $report->status);
        self::assertContains('checksum_drift', $report->blockingIssues);
        self::assertSame('Legacy opportunity', DB::table('crm_deals_canonical_shadow')->value('title'));
    }

    public function test_unknown_or_mixed_shape_fails_closed_without_expansion(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->unsignedBigInteger('crm_stage_id');
            $table->string('name');
            $table->string('title');
        });

        $service = app(CrmLegacyUuidReconciliation::class);
        self::assertSame('unknown', $service->shape());
        self::assertSame('unknown_shape', $service->expand()->status);
        self::assertFalse(Schema::hasTable('crm_deals_canonical_shadow'));
    }

    public function test_pipeline_stage_owner_close_and_value_anomalies_are_quarantined_and_block_validation(): void
    {
        $cases = [
            'pipeline tenant mismatch' => ['pipeline_id' => 8, 'expected' => 'pipeline_tenant_mismatch'],
            'stage tenant mismatch' => ['stage_id' => 10, 'expected' => 'stage_tenant_mismatch'],
            'owner tenant mismatch' => ['owner_id' => '00000000-0000-0000-0000-000000000901', 'expected' => 'owner_tenant_mismatch'],
            'ambiguous close' => ['status' => 'won', 'won_at' => '2026-08-01 12:00:00', 'lost_at' => '2026-08-02 12:00:00', 'expected' => 'ambiguous_close_state'],
            'value overflow' => ['value' => '10000000000.00', 'expected' => 'value_overflow'],
        ];

        foreach ($cases as $overrides) {
            $this->setUpLegacyFixture();
            if (($overrides['pipeline_id'] ?? null) === 8) {
                DB::table('crm_pipelines')->insert(['id' => 8, 'tenant_id' => '00000000-0000-0000-0000-000000000002', 'name' => 'Other']);
            }
            if (($overrides['stage_id'] ?? null) === 10) {
                DB::table('crm_stages')->insert(['id' => 10, 'tenant_id' => '00000000-0000-0000-0000-000000000002', 'crm_pipeline_id' => 8, 'name' => 'Other']);
            }
            if (($overrides['owner_id'] ?? null) !== null) {
                DB::table('users')->updateOrInsert(
                    ['id' => '00000000-0000-0000-0000-000000000901'],
                    ['name' => 'Other tenant owner', 'email' => 'other-owner@example.test', 'password' => 'secret', 'role' => 'user', 'created_at' => now(), 'updated_at' => now()],
                );
                if (Schema::hasTable('tenant_memberships')) {
                    DB::table('tenant_memberships')->updateOrInsert(
                        ['id' => '00000000-0000-0000-0000-000000000902'],
                        ['tenant_id' => '00000000-0000-0000-0000-000000000002', 'user_id' => '00000000-0000-0000-0000-000000000901', 'role' => 'member', 'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
            $expected = $overrides['expected'];
            unset($overrides['expected']);
            DB::table('crm_deals')->where('id', '00000000-0000-0000-0000-000000000101')->update($overrides);
            $service = app(CrmLegacyUuidReconciliation::class);
            self::assertSame('legacy_ready', $service->expand()->status);
            $backfill = $service->backfill();
            self::assertContains($backfill->status, ['legacy_quarantined', 'blocked']);
            self::assertContains($expected, $backfill->quarantineCodes);
            self::assertSame('quarantined', DB::table('crm_deal_legacy_id_map')->value('state'));
            self::assertSame('blocked', $service->validate()->status);
        }
    }

    public function test_orphan_activity_and_tag_references_are_reported_without_child_writes(): void
    {
        $this->setUpLegacyFixture();
        DB::table('crm_activities')->insert(['id' => '00000000-0000-0000-0000-000000000402', 'tenant_id' => '00000000-0000-0000-0000-000000000001', 'deal_id' => '00000000-0000-0000-0000-000000000999']);
        DB::table('deal_tag')->insert(['deal_id' => '00000000-0000-0000-0000-000000000999', 'tag_id' => '00000000-0000-0000-0000-000000000501']);
        $service = app(CrmLegacyUuidReconciliation::class);
        $service->expand();
        $report = $service->backfill();

        self::assertSame('blocked', $report->status);
        self::assertSame(2, $report->childGaps);
        self::assertSame(1, $report->childGapsByType['activities']);
        self::assertSame(1, $report->childGapsByType['deal_tags']);
        self::assertSame(1, DB::table('crm_activities')->whereNotNull('canonical_deal_id')->count());
        self::assertSame(1, DB::table('deal_tag')->whereNotNull('canonical_deal_id')->count());
    }

    public function test_bounded_resume_does_not_skip_source_order(): void
    {
        $this->setUpLegacyFixture();
        $service = app(CrmLegacyUuidReconciliation::class);
        $service->expand();

        $empty = $service->backfill(limit: 1, offset: 1);
        self::assertSame(0, $empty->processedRows);
        self::assertSame(0, $empty->mappedRows);
        self::assertSame(1, $empty->offset);
        self::assertSame(1, $empty->details['next_offset']);
        self::assertSame(0, DB::table('crm_deal_legacy_id_map')->count());

        $first = $service->backfill(limit: 1, offset: 0);
        self::assertSame(1, $first->mappedRows);
        self::assertSame(1, $first->details['next_offset']);
        $this->expectException(\InvalidArgumentException::class);
        $service->backfill(limit: 0);
    }

    public function test_two_tenants_have_independent_exact_mappings_and_bounded_batches(): void
    {
        $this->setUpLegacyFixture();
        DB::table('crm_pipelines')->insert(['id' => 8, 'tenant_id' => '00000000-0000-0000-0000-000000000002', 'name' => 'Second']);
        DB::table('crm_stages')->insert(['id' => 10, 'tenant_id' => '00000000-0000-0000-0000-000000000002', 'crm_pipeline_id' => 8, 'name' => 'New']);
        $source = (array) DB::table('crm_deals')->first();
        $source['id'] = '00000000-0000-0000-0000-000000000102';
        $source['tenant_id'] = '00000000-0000-0000-0000-000000000002';
        $source['pipeline_id'] = 8;
        $source['stage_id'] = 10;
        $source['created_at'] = now();
        $source['updated_at'] = now();
        DB::table('crm_deals')->insert($source);

        $service = app(CrmLegacyUuidReconciliation::class);
        $service->expand();
        self::assertSame('legacy_pending', $service->backfill(limit: 1, offset: 0)->status);
        self::assertSame('legacy_ready', $service->backfill(limit: 1, offset: 1)->status);
        self::assertSame(2, DB::table('crm_deal_legacy_id_map')->where('state', 'mapped')->count());
        self::assertSame(2, DB::table('crm_deals_canonical_shadow')->distinct('tenant_id')->count('tenant_id'));
        self::assertSame('legacy_ready', $service->validate()->status);
    }

    public function test_expand_down_is_non_destructive_for_legacy_and_reconciliation_structures(): void
    {
        $this->setUpLegacyFixture();
        $migration = require database_path('migrations/2026_08_21_000001_expand_crm_legacy_uuid_reconciliation.php');
        $migration->up();
        DB::table('crm_deals_canonical_shadow')->insert([
            'tenant_id' => '00000000-0000-0000-0000-000000000001',
            'legacy_uuid' => '00000000-0000-0000-0000-000000000101',
            'crm_pipeline_id' => 7,
            'crm_stage_id' => 9,
            'title' => 'Shadow',
            'value' => '1.00',
            'currency' => 'HNL',
            'status' => 'open',
        ]);
        $migration->down();

        self::assertTrue(Schema::hasTable('crm_deals'));
        self::assertTrue(Schema::hasTable('crm_deals_canonical_shadow'));
        self::assertTrue(Schema::hasTable('crm_deal_legacy_id_map'));
        self::assertTrue(Schema::hasTable('crm_deal_legacy_payloads'));
        self::assertTrue(Schema::hasColumn('crm_activities', 'canonical_deal_id'));
        self::assertTrue(Schema::hasColumn('deal_tag', 'canonical_deal_id'));
        self::assertSame(1, DB::table('crm_deals')->count());
        self::assertSame(1, DB::table('crm_deals_canonical_shadow')->count());
    }

    private function setUpLegacyFixture(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['deal_tag', 'crm_activities', 'tags', 'crm_deals_canonical_shadow', 'crm_deal_legacy_id_map', 'crm_deal_legacy_payloads', 'crm_deals', 'crm_stages', 'crm_pipelines'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('tenants')->updateOrInsert(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['user_id' => null, 'name' => 'Legacy tenant', 'slug' => 'legacy-reconciliation', 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('tenants')->updateOrInsert(
            ['id' => '00000000-0000-0000-0000-000000000002'],
            ['user_id' => null, 'name' => 'Second tenant', 'slug' => 'legacy-reconciliation-2', 'created_at' => now(), 'updated_at' => now()],
        );
        Schema::create('crm_pipelines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('name');
        });
        Schema::create('crm_stages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->string('name');
        });
        DB::table('crm_pipelines')->insert(['id' => 7, 'tenant_id' => '00000000-0000-0000-0000-000000000001', 'name' => 'Default']);
        DB::table('crm_stages')->insert(['id' => 9, 'tenant_id' => '00000000-0000-0000-0000-000000000001', 'crm_pipeline_id' => 7, 'name' => 'New']);
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->uuid('contact_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->string('name', 200);
            $table->decimal('value', 14, 2)->default(0);
            $table->string('currency', 3)->default('HNL');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('expected_close_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('write_idempotency_key', 160)->nullable();
            $table->char('write_payload_hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('crm_deals')->insert([
            'id' => '00000000-0000-0000-0000-000000000101',
            'tenant_id' => '00000000-0000-0000-0000-000000000001',
            'pipeline_id' => 7,
            'stage_id' => 9,
            'contact_id' => '00000000-0000-0000-0000-000000000201',
            'company_id' => '00000000-0000-0000-0000-000000000301',
            'owner_id' => null,
            'name' => 'Legacy opportunity',
            'value' => '1250.00',
            'currency' => 'hnl',
            'probability' => 50,
            'expected_close_date' => '2026-08-31',
            'status' => 'open',
            'won_at' => null,
            'lost_at' => null,
            'lost_reason' => null,
            'description' => 'Existing CRM data.',
            'custom_fields' => json_encode(['legacy' => true]),
            'write_idempotency_key' => 'legacy-key',
            'write_payload_hash' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('deal_id')->nullable();
        });
        DB::table('crm_activities')->insert(['id' => '00000000-0000-0000-0000-000000000401', 'tenant_id' => '00000000-0000-0000-0000-000000000001', 'deal_id' => '00000000-0000-0000-0000-000000000101']);
        Schema::create('tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
        });
        DB::table('tags')->insert(['id' => '00000000-0000-0000-0000-000000000501', 'tenant_id' => '00000000-0000-0000-0000-000000000001', 'name' => 'legacy']);
        Schema::create('deal_tag', function (Blueprint $table): void {
            $table->uuid('deal_id');
            $table->uuid('tag_id');
            $table->primary(['deal_id', 'tag_id']);
        });
        DB::table('deal_tag')->insert(['deal_id' => '00000000-0000-0000-0000-000000000101', 'tag_id' => '00000000-0000-0000-0000-000000000501']);
        Schema::enableForeignKeyConstraints();
    }

    private function setUpMinimalCanonicalShape(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('crm_pipeline_id');
            $table->unsignedBigInteger('crm_stage_id');
            $table->string('title');
            $table->decimal('value', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 16);
            $table->timestamps();
        });
    }
}
