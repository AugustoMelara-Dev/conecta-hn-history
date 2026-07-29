<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'ai_agent_definitions',
        'ai_agent_versions',
        'ai_prompt_definitions',
        'ai_prompt_versions',
        'ai_release_policies',
        'ai_agent_activations',
        'ai_conversations',
        'ai_conversation_participants',
        'ai_messages',
        'ai_message_evidence',
        'ai_message_failures',
        'ai_handoffs',
        'ai_evaluation_suites',
        'ai_evaluation_versions',
        'ai_evaluation_cases',
        'ai_evaluation_runs',
        'ai_evaluation_results',
        'ai_release_decisions',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->eligibleTables() as $table) {
            $identifier = $this->identifier($table);
            $policy = $this->identifier($table.'_tenant_isolation');

            DB::statement("ALTER TABLE {$identifier} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$identifier} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$policy} ON {$identifier}");
            DB::statement(
                "CREATE POLICY {$policy} ON {$identifier} "
                ."USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid) "
                ."WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->eligibleTables() as $table) {
            $identifier = $this->identifier($table);
            $policy = $this->identifier($table.'_tenant_isolation');

            DB::statement("DROP POLICY IF EXISTS {$policy} ON {$identifier}");
            DB::statement("ALTER TABLE {$identifier} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$identifier} DISABLE ROW LEVEL SECURITY");
        }
    }

    /** @return list<string> */
    private function eligibleTables(): array
    {
        return array_values(array_filter(
            $this->tables,
            static fn (string $table): bool => Schema::hasTable($table)
                && Schema::hasColumn($table, 'tenant_id'),
        ));
    }

    private function identifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
};
