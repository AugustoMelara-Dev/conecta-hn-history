<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const VERSION_TABLES = [
        'ai_provider_versions' => 'aiprv_lifecycle_kill_idx',
        'ai_model_versions' => 'aimv_lifecycle_kill_idx',
        'ai_agent_versions' => 'aiav_lifecycle_kill_idx',
        'ai_prompt_versions' => 'aipv_lifecycle_kill_idx',
    ];

    public function up(): void
    {
        foreach (self::VERSION_TABLES as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->string('lifecycle_status', 24)->default('draft');
                $table->boolean('kill_switch_enabled')->default(false);
                $table->timestamp('blocked_at')->nullable();
                $table->string('blocked_by', 64)->nullable();
                $table->text('blocked_reason')->nullable();
                $table->index(['lifecycle_status', 'kill_switch_enabled'], $indexName);
            });

            DB::table($tableName)
                ->whereNotNull('released_at')
                ->update(['lifecycle_status' => 'active']);
        }

        DB::table('ai_provider_versions')
            ->where('status', 'blocked')
            ->update([
                'lifecycle_status' => 'blocked',
                'kill_switch_enabled' => true,
                'blocked_at' => now(),
                'blocked_by' => 'registry_backfill',
                'blocked_reason' => 'Existing provider version was explicitly blocked.',
            ]);

        Schema::table('ai_agent_activations', function (Blueprint $table): void {
            $table->string('activated_by', 64)->default('registry_backfill');
            $table->text('reason')->default('Program 05 lifecycle backfill');
            $table->unsignedInteger('lock_version')->default(0);
            $table->index(
                ['tenant_id', 'scope_type', 'scope_key', 'effective_until'],
                'aiact_open_scope_idx',
            );
        });

        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            $table->char('candidate_checksum', 64)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by', 64)->nullable();
            $table->text('revocation_reason')->nullable();
            $table->index(
                ['tenant_id', 'agent_version_id', 'valid_from'],
                'aird_agent_valid_from_idx',
            );
            $table->index(['tenant_id', 'revoked_at'], 'aird_tenant_revoked_idx');
        });

        DB::table('ai_release_decisions')
            ->select(['id', 'agent_version_id', 'decided_at'])
            ->orderBy('id')
            ->each(function (object $decision): void {
                $checksum = is_string($decision->agent_version_id)
                    ? DB::table('ai_agent_versions')
                        ->where('id', $decision->agent_version_id)
                        ->value('checksum')
                    : null;

                DB::table('ai_release_decisions')
                    ->where('id', $decision->id)
                    ->update([
                        'candidate_checksum' => is_string($checksum) ? $checksum : null,
                        'valid_from' => $decision->decided_at,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            $table->dropIndex('aird_agent_valid_from_idx');
            $table->dropIndex('aird_tenant_revoked_idx');
            $table->dropColumn([
                'candidate_checksum',
                'valid_from',
                'valid_until',
                'revoked_at',
                'revoked_by',
                'revocation_reason',
            ]);
        });

        Schema::table('ai_agent_activations', function (Blueprint $table): void {
            $table->dropIndex('aiact_open_scope_idx');
            $table->dropColumn(['activated_by', 'reason', 'lock_version']);
        });

        foreach (array_reverse(self::VERSION_TABLES, true) as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
                $table->dropColumn([
                    'lifecycle_status',
                    'kill_switch_enabled',
                    'blocked_at',
                    'blocked_by',
                    'blocked_reason',
                ]);
            });
        }
    }
};
