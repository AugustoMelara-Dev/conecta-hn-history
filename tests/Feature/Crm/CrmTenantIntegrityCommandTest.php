<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Application\Crm\TenantIntegrity\CrmTenantIntegrity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrmTenantIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_command_emits_deterministic_json_for_canonical_shape(): void
    {
        $this->canonicalDealsTable();

        $report = app(CrmTenantIntegrity::class)->preflight();
        self::assertSame('READY', $report->status, json_encode($report->toArray(), JSON_THROW_ON_ERROR));
        $this->artisan('crm:reconcile-tenant-integrity', ['phase' => 'preflight', '--json' => true])
            ->expectsOutputToContain('"shape": "canonical_bigint"')
            ->assertSuccessful();
    }

    public function test_expand_command_requires_current_fingerprint_and_refuses_legacy_shape(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::create('crm_deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('name');
        });

        $this->artisan('crm:reconcile-tenant-integrity', ['phase' => 'expand', '--json' => true])
            ->expectsOutputToContain('"status": "BLOCKED_LEGACY_UUID_CONTRACT"')
            ->assertExitCode(5);
    }

    public function test_unknown_phase_is_invalid_without_database_changes(): void
    {
        $this->artisan('crm:reconcile-tenant-integrity', ['phase' => 'nonsense', '--json' => true])
            ->assertExitCode(2);
    }

    public function test_backfill_command_writes_redacted_checkpoint_and_resumes_bounded_rows(): void
    {
        $fixture = $this->prepareBackfillFixture(contactRows: 1, dealRows: 1);
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-command-');
        self::assertNotFalse($checkpoint);
        @unlink($checkpoint);

        $this->artisan('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--limit' => 1,
            '--checkpoint' => $checkpoint,
            '--json' => true,
        ])->expectsOutputToContain('"status": "BACKFILLED"')->assertSuccessful();

        $checkpointPayload = json_decode((string) file_get_contents($checkpoint), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $checkpointPayload['schema']);
        self::assertSame(1, $checkpointPayload['version']);
        self::assertSame('crm:reconcile-tenant-integrity', $checkpointPayload['command']);
        self::assertSame('backfill', $checkpointPayload['phase']);
        self::assertSame(1, $checkpointPayload['sequence']);
        self::assertTrue($checkpointPayload['has_more']);
        foreach (['schema_hash', 'data_hash', 'source_hash', 'validation_chain', 'checksum'] as $key) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($checkpointPayload[$key] ?? ''));
        }
        self::assertStringNotContainsString($fixture['contact_a'], (string) file_get_contents($checkpoint));
        self::assertStringNotContainsString($fixture['tag_a'], (string) file_get_contents($checkpoint));

        $this->artisan('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--limit' => 1,
            '--checkpoint' => $checkpoint,
            '--resume' => true,
            '--json' => true,
        ])->expectsOutputToContain('"status": "BACKFILLED"')->assertSuccessful();

        self::assertSame(0, DB::table('contact_tag')->whereNull('tenant_id')->count());
        self::assertSame(0, DB::table('deal_tag')->whereNull('tenant_id')->count());
        @unlink($checkpoint);
    }

    public function test_backfill_command_dry_run_preserves_database_and_checkpoint(): void
    {
        $this->prepareBackfillFixture(contactRows: 1, dealRows: 0);
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-command-dry-');
        self::assertNotFalse($checkpoint);
        $sentinel = '{"untouched":true}';
        file_put_contents($checkpoint, $sentinel);

        $this->artisan('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--limit' => 1,
            '--checkpoint' => $checkpoint,
            '--dry-run' => true,
            '--json' => true,
        ])->expectsOutputToContain('"status": "BACKFILL_DRY_RUN"')->assertSuccessful();

        self::assertSame(1, DB::table('contact_tag')->whereNull('tenant_id')->count());
        self::assertSame($sentinel, file_get_contents($checkpoint));
        @unlink($checkpoint);
    }

    public function test_backfill_command_fails_closed_for_tampered_checkpoint(): void
    {
        $this->prepareBackfillFixture(contactRows: 1, dealRows: 0);
        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-command-tamper-');
        self::assertNotFalse($checkpoint);
        @unlink($checkpoint);
        $this->artisan('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--limit' => 1,
            '--checkpoint' => $checkpoint,
            '--json' => true,
        ])->assertSuccessful();
        $payload = json_decode((string) file_get_contents($checkpoint), true, 512, JSON_THROW_ON_ERROR);
        $payload['sequence'] = 99;
        file_put_contents($checkpoint, json_encode($payload, JSON_THROW_ON_ERROR));

        $tamperedExit = Artisan::call('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--limit' => 1,
            '--checkpoint' => $checkpoint,
            '--resume' => true,
            '--json' => true,
        ]);
        $tamperedError = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $tamperedExit, json_encode($tamperedError, JSON_THROW_ON_ERROR));
        self::assertSame('failed', $tamperedError['status'] ?? null);
        self::assertSame('checkpoint_invalid', $tamperedError['code'] ?? null);
        self::assertSame(0, DB::table('contact_tag')->whereNull('tenant_id')->count());
        @unlink($checkpoint);
    }

    public function test_backfill_command_blocks_unproved_non_sqlite_engine_without_writes(): void
    {
        $original = (string) config('database.default');
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        try {
            $this->artisan('crm:reconcile-tenant-integrity', [
                'phase' => 'backfill',
                '--json' => true,
            ])->expectsOutputToContain('"status": "EXTERNAL_BLOCKED_ENGINE_PROOF_REQUIRED"')->assertExitCode(5);
        } finally {
            config(['database.default' => $original]);
            DB::purge('mysql');
        }
    }

    public function test_json_invalid_limit_and_phase_emit_stable_error_objects(): void
    {
        $invalidLimitExit = Artisan::call('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--limit' => 0,
            '--json' => true,
        ]);
        $invalidLimit = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $invalidLimitExit);
        self::assertSame('failed', $invalidLimit['status'] ?? null);
        self::assertSame('invalid_argument', $invalidLimit['code'] ?? null);

        $invalidPhaseExit = Artisan::call('crm:reconcile-tenant-integrity', [
            'phase' => 'not-a-phase',
            '--json' => true,
        ]);
        $invalidPhase = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $invalidPhaseExit);
        self::assertSame('failed', $invalidPhase['status'] ?? null);
        self::assertSame('invalid_argument', $invalidPhase['code'] ?? null);
    }

    public function test_json_invalid_checkpoint_errors_are_stable_error_objects(): void
    {
        $missingExit = Artisan::call('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--resume' => true,
            '--json' => true,
        ]);
        $missing = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $missingExit);
        self::assertSame('failed', $missing['status'] ?? null);
        self::assertSame('checkpoint_required', $missing['code'] ?? null);

        $checkpoint = tempnam(sys_get_temp_dir(), 'crm-tenant-integrity-command-invalid-');
        self::assertNotFalse($checkpoint);
        file_put_contents($checkpoint, '{"tampered":true}');
        $invalidExit = Artisan::call('crm:reconcile-tenant-integrity', [
            'phase' => 'backfill',
            '--checkpoint' => $checkpoint,
            '--resume' => true,
            '--json' => true,
        ]);
        $invalid = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $invalidExit);
        self::assertSame('failed', $invalid['status'] ?? null);
        self::assertSame('checkpoint_invalid', $invalid['code'] ?? null);
        @unlink($checkpoint);
    }

    /** @return array<string, string> */
    private function prepareBackfillFixture(int $contactRows = 2, int $dealRows = 1): array
    {
        $this->canonicalDealsTable();
        $service = app(CrmTenantIntegrity::class);
        $preflight = $service->preflight();
        self::assertSame('EXPANDED', $service->expand($preflight->schemaFingerprint)->status);
        $tenantA = '00000000-0000-0000-0000-000000000001';
        $tenantB = '00000000-0000-0000-0000-000000000002';
        DB::table('tenants')->insert([
            ['id' => $tenantA, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $tenantB, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_pipelines')->insert(['id' => 1, 'tenant_id' => $tenantA, 'name' => 'Pipeline A', 'key' => 'pipeline-a', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_stages')->insert(['id' => 1, 'tenant_id' => $tenantA, 'crm_pipeline_id' => 1, 'name' => 'Stage A', 'key' => 'stage-a', 'created_at' => now(), 'updated_at' => now()]);
        $contacts = [];
        $tags = [];
        for ($i = 0; $i < max($contactRows, $dealRows, 1); $i++) {
            $contact = sprintf('00000000-0000-0000-0000-000000003%03d', $i + 1);
            $tag = sprintf('00000000-0000-0000-0000-000000004%03d', $i + 1);
            DB::table('crm_contacts')->insert(['id' => $contact, 'tenant_id' => $tenantA, 'company_id' => null, 'owner_id' => null, 'first_name' => 'Fixture', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('tags')->insert(['id' => $tag, 'tenant_id' => $tenantA, 'name' => 'Fixture', 'slug' => 'fixture-'.$i, 'created_at' => now(), 'updated_at' => now()]);
            $contacts[] = $contact;
            $tags[] = $tag;
        }
        DB::table('crm_deals')->insert(array_map(static fn (int $i): array => [
            'id' => 9900 + $i,
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
        ], range(0, max(0, $dealRows - 1))));
        for ($i = 0; $i < $contactRows; $i++) {
            DB::table('contact_tag')->insert(['contact_id' => $contacts[$i], 'tag_id' => $tags[$i], 'tenant_id' => null]);
        }
        for ($i = 0; $i < $dealRows; $i++) {
            DB::table('deal_tag')->insert(['deal_id' => 9900 + $i, 'tag_id' => $tags[$i], 'tenant_id' => null]);
        }

        return ['contact_a' => $contacts[0], 'tag_a' => $tags[0]];
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
            $table->uuid('location_id')->nullable();
            $table->uuid('owner_user_id')->nullable();
            $table->string('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('HNL');
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }
}
