<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AiRegistryLifecycleMigrationMySqlTest extends TestCase
{
    public function test_mysql_84_up_backfill_and_down_are_portable(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('This directed migration harness requires MySQL 8.4.');
        }
        $version = (string) DB::scalar('select version()');
        self::assertStringStartsWith('8.4.', $version);

        $tables = [
            'ai_release_decisions',
            'ai_agent_activations',
            'ai_prompt_versions',
            'ai_agent_versions',
            'ai_model_versions',
            'ai_provider_versions',
        ];
        try {
            $this->createPrerequisites();
            $agentVersionId = (string) Str::uuid();
            DB::table('ai_provider_versions')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'status' => 'active',
                    'released_at' => null,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'status' => 'blocked',
                    'released_at' => now()->subMinute(),
                ],
            ]);
            DB::table('ai_model_versions')->insert([
                'id' => (string) Str::uuid(),
                'released_at' => now()->subMinute(),
            ]);
            DB::table('ai_agent_versions')->insert([
                'id' => $agentVersionId,
                'checksum' => hash('sha256', $agentVersionId),
                'released_at' => now()->subMinute(),
            ]);
            DB::table('ai_prompt_versions')->insert([
                'id' => (string) Str::uuid(),
                'released_at' => null,
            ]);
            $decisionId = (string) Str::uuid();
            DB::table('ai_release_decisions')->insert([
                'id' => $decisionId,
                'tenant_id' => (string) Str::uuid(),
                'agent_version_id' => $agentVersionId,
                'decided_at' => now(),
            ]);

            $migration = require database_path(
                'migrations/2026_07_29_000006_expand_ai_registry_lifecycle.php',
            );
            $migration->up();

            self::assertTrue(Schema::hasColumns('ai_agent_activations', [
                'activated_by',
                'reason',
                'lock_version',
            ]));
            $reason = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'ai_agent_activations')
                ->where('column_name', 'reason')
                ->first();
            self::assertNotNull($reason);
            self::assertSame('varchar', strtolower((string) $reason->DATA_TYPE));
            self::assertSame(500, (int) $reason->CHARACTER_MAXIMUM_LENGTH);
            self::assertSame(
                'active',
                DB::table('ai_agent_versions')->where('id', $agentVersionId)
                    ->value('lifecycle_status'),
            );
            self::assertSame(
                'blocked',
                DB::table('ai_provider_versions')->where('status', 'blocked')
                    ->value('lifecycle_status'),
            );
            self::assertSame(
                hash('sha256', $agentVersionId),
                DB::table('ai_release_decisions')->where('id', $decisionId)
                    ->value('candidate_checksum'),
            );

            $migration->down();
            self::assertFalse(Schema::hasColumn('ai_agent_activations', 'reason'));
            self::assertFalse(Schema::hasColumn('ai_agent_versions', 'lifecycle_status'));
            self::assertFalse(Schema::hasColumn('ai_release_decisions', 'candidate_checksum'));
        } finally {
            Schema::disableForeignKeyConstraints();
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }
            Schema::enableForeignKeyConstraints();
        }
    }

    private function createPrerequisites(): void
    {
        Schema::create('ai_provider_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 24);
            $table->timestamp('released_at')->nullable();
        });
        foreach (['ai_model_versions', 'ai_prompt_versions'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->timestamp('released_at')->nullable();
            });
        }
        Schema::create('ai_agent_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('checksum', 64);
            $table->timestamp('released_at')->nullable();
        });
        Schema::create('ai_agent_activations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('scope_type', 32);
            $table->string('scope_key', 190);
            $table->timestamp('effective_until')->nullable();
        });
        Schema::create('ai_release_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('agent_version_id')->nullable();
            $table->timestamp('decided_at')->nullable();
        });
    }
}
