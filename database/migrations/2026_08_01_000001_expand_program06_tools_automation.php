<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tenantTables = [
        'automation_workflow_definitions',
        'automation_workflow_versions',
        'automation_workflow_runs',
        'automation_workflow_steps',
        'automation_workflow_step_attempts',
        'automation_workflow_approvals',
        'automation_tool_policies',
        'automation_confirmation_attempts',
        'automation_webhook_deliveries',
        'automation_integration_idempotency',
    ];

    public function up(): void
    {
        Schema::create('automation_workflow_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('workflow_key', 100);
            $table->uuid('current_draft_version_id')->nullable();
            $table->uuid('current_published_version_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'workflow_key']);
        });

        Schema::create('automation_workflow_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('definition_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('workflow_key', 100);
            $table->string('version', 100);
            $table->string('status');
            $table->json('definition');
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'workflow_key', 'version'], 'automation_workflow_version_unique');
        });

        Schema::create('automation_workflow_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('workflow_key', 100);
            $table->uuid('workflow_version_id')->index();
            $table->string('workflow_version', 100);
            $table->uuid('actor_id')->nullable();
            $table->string('plan');
            $table->string('channel');
            $table->string('trigger_source');
            $table->string('idempotency_key', 100);
            $table->string('status');
            $table->string('current_step_key')->nullable();
            $table->json('payload');
            $table->json('output')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestamp('waiting_until')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'workflow_key', 'workflow_version', 'idempotency_key'], 'automation_runs_unique');
        });

        Schema::create('automation_workflow_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('step_key', 100);
            $table->string('step_type');
            $table->string('action')->nullable();
            $table->string('status');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->json('output')->nullable();
            $table->string('compensation_action')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'step_key']);
        });

        Schema::create('automation_workflow_step_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('step_key', 100);
            $table->string('attempt_kind', 100);
            $table->unsignedInteger('attempt_number');
            $table->string('provider_tool_key');
            $table->string('idempotency_key', 100);
            $table->string('action')->nullable();
            $table->string('status');
            $table->string('error_code')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'step_key', 'attempt_kind', 'attempt_number'], 'automation_step_attempt_unique');
            $table->unique(['tenant_id', 'idempotency_key'], 'automation_step_attempt_idempotency_unique');
        });

        Schema::create('automation_workflow_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('step_key', 100);
            $table->uuid('actor_id')->nullable();
            $table->string('status');
            $table->text('reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_tool_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('tool', 100);
            $table->string('tool_version', 100);
            $table->json('allowed_roles');
            $table->json('allowed_plans');
            $table->json('allowed_channels');
            $table->string('max_risk');
            $table->boolean('confirmation_required')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'tool', 'tool_version']);
        });

        Schema::create('automation_confirmation_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('actor_id')->index();
            $table->string('tool', 100);
            $table->string('tool_version', 100);
            $table->string('payload_hash', 64);
            $table->string('confirmation_token_hash', 64);
            $table->string('status');
            $table->text('encrypted_payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_webhook_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('actor_id')->index();
            $table->string('provider', 100);
            $table->string('market');
            $table->string('version', 100);
            $table->string('capability');
            $table->string('external_id', 100);
            $table->string('payload_hash', 64);
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'provider', 'external_id']);
        });

        Schema::create('automation_integration_idempotency', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('scope', 100);
            $table->string('idempotency_key', 100);
            $table->string('fingerprint', 64);
            $table->json('outcome')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'scope', 'idempotency_key'], 'automation_idempotency_unique');
        });

        $this->applyTenantRls();
    }

    public function down(): void
    {
        foreach (array_reverse($this->tenantTables) as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function applyTenantRls(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tenantTables as $table) {
            $identifier = sprintf('"%s"', $table);
            $policy = sprintf('%s_tenant_isolation', $table);

            DB::statement("ALTER TABLE {$identifier} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$identifier} FORCE ROW LEVEL SECURITY");
            DB::statement(
                "CREATE POLICY {$policy} ON {$identifier} USING (tenant_id::text = current_setting('app.tenant_id', true))"
            );
        }
    }
};
