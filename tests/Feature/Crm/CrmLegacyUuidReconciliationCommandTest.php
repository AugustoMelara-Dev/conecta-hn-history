<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmLegacyUuidReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_command_is_blocked_and_emits_json_without_writes(): void
    {
        $this->setUpCanonicalShape();
        $beforeTables = array_map(static fn (mixed $table): string => is_object($table) ? (string) ($table->name ?? '') : (string) ($table['name'] ?? ''), Schema::getTables());

        $this->artisan('crm:reconcile-legacy-uuid', [
            '--phase' => 'contract',
            '--json' => true,
        ])->expectsOutputToContain('"status": "blocked"')
            ->assertExitCode(5);

        $afterTables = array_map(static fn (mixed $table): string => is_object($table) ? (string) ($table->name ?? '') : (string) ($table['name'] ?? ''), Schema::getTables());
        self::assertSame($beforeTables, $afterTables);
        self::assertSame(0, DB::table('crm_deals')->count());
    }

    public function test_unknown_shape_command_fails_closed_with_nonzero_exit(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('unrecognized_field');
        });

        $this->artisan('crm:reconcile-legacy-uuid', ['phase' => 'expand', '--json' => true])
            ->expectsOutputToContain('"status": "unknown_shape"')
            ->assertExitCode(6);
    }

    public function test_backfill_command_emits_ready_json_for_exact_legacy_fixture(): void
    {
        $this->setUpLegacyCommandFixture(value: '1250.00');
        $this->artisan('crm:reconcile-legacy-uuid', ['phase' => 'expand', '--json' => true])->assertSuccessful();
        $this->artisan('crm:reconcile-legacy-uuid', ['phase' => 'backfill', '--limit' => 1, '--json' => true])
            ->expectsOutputToContain('"status": "legacy_ready"')
            ->assertSuccessful();
        self::assertSame(1, DB::table('crm_deal_legacy_id_map')->where('state', 'mapped')->count());
    }

    public function test_backfill_command_returns_nonzero_for_quarantined_source(): void
    {
        $this->setUpLegacyCommandFixture(value: '10000000000.00');
        $this->artisan('crm:reconcile-legacy-uuid', ['phase' => 'expand', '--json' => true])->assertSuccessful();
        $this->artisan('crm:reconcile-legacy-uuid', ['phase' => 'backfill', '--json' => true])
            ->expectsOutputToContain('"status": "legacy_quarantined"')
            ->assertExitCode(5);
        self::assertSame('value_overflow', DB::table('crm_deal_legacy_id_map')->value('error_code'));
    }

    private function setUpCanonicalShape(): void
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

    private function setUpLegacyCommandFixture(string $value): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['crm_deals_canonical_shadow', 'crm_deal_legacy_id_map', 'crm_deal_legacy_payloads', 'crm_deals', 'crm_stages', 'crm_pipelines'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('tenants')->updateOrInsert(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['user_id' => null, 'name' => 'Legacy tenant', 'slug' => 'legacy-command', 'created_at' => now(), 'updated_at' => now()],
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
            $table->uuid('owner_id')->nullable();
            $table->string('name');
            $table->decimal('value', 14, 2);
            $table->string('currency', 3);
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('expected_close_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();
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
            'name' => 'Command fixture',
            'value' => $value,
            'currency' => 'HNL',
            'probability' => 50,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }
}
