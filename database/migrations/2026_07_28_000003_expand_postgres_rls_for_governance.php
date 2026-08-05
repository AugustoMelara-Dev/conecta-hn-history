<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'tenant_memberships',
        'tenant_invitations',
        'impersonation_grants',
        'tenant_markets',
        'locations',
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
