<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\DynamicForms\ProcessDynamicReindexChunkJob;
use App\Models\Dynamic\DynamicReindexRun;
use App\Models\Dynamic\EntityDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DynamicFormsReindexPipelineCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        $this->createMinimalSchema();
    }

    public function test_dispatch_command_creates_run_and_queues_first_chunk_job(): void
    {
        [$tenantId, $entity] = $this->seedDynamicFixture();
        Queue::fake();

        $exitCode = Artisan::call('dynamic-forms:reindex-dispatch', [
            'entity' => (string) $entity->getKey(),
            '--tenant' => $tenantId,
            '--chunk' => 100,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $runId = (string) ($report['run_id'] ?? '');

        $this->assertNotSame('', $runId);
        $this->assertSame('queued', $report['status']);
        $this->assertSame(1, (int) $report['total_records']);

        $this->assertDatabaseHas('dynamic_reindex_runs', [
            'id' => $runId,
            'entity_definition_id' => (string) $entity->getKey(),
            'tenant_id' => $tenantId,
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessDynamicReindexChunkJob::class, function (ProcessDynamicReindexChunkJob $job) use ($runId): bool {
            return $job->runId === $runId
                && $job->chunkNumber === 1
                && $job->cursorStart === null;
        });
    }

    public function test_status_command_returns_run_and_checkpoint_state(): void
    {
        [$tenantId, $entity] = $this->seedDynamicFixture();

        $run = DynamicReindexRun::query()->create([
            'tenant_id' => $tenantId,
            'entity_definition_id' => $entity->getKey(),
            'status' => 'running',
            'chunk_size' => 100,
            'total_records' => 10,
            'processed_records' => 4,
            'failed_records' => 1,
            'started_at' => now()->subMinute(),
        ]);

        $run->checkpoints()->create([
            'chunk_number' => 1,
            'status' => 'completed',
            'cursor_start' => null,
            'cursor_end' => (string) Str::uuid(),
            'processed_count' => 4,
            'failed_count' => 1,
            'processed_at' => now(),
        ]);

        $exitCode = Artisan::call('dynamic-forms:reindex-status', [
            'run' => (string) $run->getKey(),
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((string) $run->getKey(), $report['run']['id']);
        $this->assertSame('running', $report['run']['status']);
        $this->assertCount(1, $report['checkpoints']);
    }

    public function test_resume_command_queues_next_chunk_from_last_cursor(): void
    {
        [$tenantId, $entity] = $this->seedDynamicFixture();
        Queue::fake();

        $cursor = (string) Str::uuid();
        $run = DynamicReindexRun::query()->create([
            'tenant_id' => $tenantId,
            'entity_definition_id' => $entity->getKey(),
            'status' => 'failed',
            'chunk_size' => 50,
            'total_records' => 100,
            'processed_records' => 50,
            'failed_records' => 2,
            'last_record_id' => $cursor,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinute(),
        ]);

        $run->checkpoints()->create([
            'chunk_number' => 1,
            'status' => 'completed',
            'cursor_start' => null,
            'cursor_end' => $cursor,
            'processed_count' => 50,
            'failed_count' => 2,
            'processed_at' => now(),
        ]);

        $exitCode = Artisan::call('dynamic-forms:reindex-resume', [
            'run' => (string) $run->getKey(),
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('queued', $report['status']);

        Queue::assertPushed(ProcessDynamicReindexChunkJob::class, function (ProcessDynamicReindexChunkJob $job) use ($run, $cursor): bool {
            return $job->runId === (string) $run->getKey()
                && $job->chunkNumber === 2
                && $job->cursorStart === $cursor;
        });
    }

    /**
     * @return array{0: string, 1: EntityDefinition}
     */
    private function seedDynamicFixture(): array
    {
        $tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAsTenant($tenantId);

        $entity = EntityDefinition::create([
            'tenant_id' => $tenantId,
            'owner_scope' => 'tenant',
            'module_key' => 'operations',
            'entity_key' => 'reindex_visit',
            'name' => 'Reindex Visit',
            'status' => 'draft',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        DB::table('dynamic_records')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'entity_definition_id' => (string) $entity->getKey(),
            'status' => 'active',
            'payload_json' => json_encode(['patient_name' => 'Maria Lopez'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenantId, $entity->fresh()];
    }

    private function createMinimalSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
        });

        Schema::create('entity_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('owner_scope', 24)->default('tenant');
            $table->string('module_key', 80);
            $table->string('entity_key', 120);
            $table->string('name');
            $table->string('status', 24)->default('draft');
            $table->boolean('allow_tenant_overrides')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('dynamic_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('entity_definition_id');
            $table->string('status', 24)->default('active');
            $table->json('payload_json');
            $table->timestamps();
        });

        Schema::create('dynamic_record_index_values', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dynamic_record_id');
            $table->uuid('tenant_id');
            $table->uuid('entity_definition_id');
            $table->string('field_key', 120);
            $table->string('value_string')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_reindex_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('entity_definition_id');
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('chunk_size')->default(200);
            $table->unsignedBigInteger('total_records')->default(0);
            $table->unsignedBigInteger('processed_records')->default(0);
            $table->unsignedBigInteger('failed_records')->default(0);
            $table->uuid('last_record_id')->nullable();
            $table->uuid('dispatched_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_reindex_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dynamic_reindex_run_id');
            $table->unsignedInteger('chunk_number');
            $table->string('status', 24)->default('running');
            $table->uuid('cursor_start')->nullable();
            $table->uuid('cursor_end')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_reindex_failures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dynamic_reindex_run_id');
            $table->unsignedBigInteger('dynamic_reindex_checkpoint_id')->nullable();
            $table->uuid('dynamic_record_id')->nullable();
            $table->unsignedInteger('chunk_number');
            $table->text('error_message');
            $table->json('context_json')->nullable();
            $table->timestamp('failed_at');
            $table->timestamps();
        });
    }
}
