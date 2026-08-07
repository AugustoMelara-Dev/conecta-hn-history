<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tenantScopedTables = [
        'dovra_privacy_requests',
        'dovra_privacy_request_events',
        'dovra_legal_holds',
        'dovra_data_retention_records',
    ];

    public function up(): void
    {
        $this->assertTablesExist();

        Schema::table('dovra_privacy_requests', function (Blueprint $table): void {
            if ($this->indexExists('dovra_privacy_requests', 'dovra_privacy_requests_idempotency_key_unique')) {
                $table->dropUnique('dovra_privacy_requests_idempotency_key_unique');
            }

            if (! $this->indexExists('dovra_privacy_requests', 'dovra_privacy_tenant_idempotency_unique')) {
                $table->unique(
                    ['tenant_id', 'actor_id', 'subject_type', 'subject_id', 'request_type', 'idempotency_key'],
                    'dovra_privacy_tenant_idempotency_unique',
                );
            }
        });

        $this->enableForcedTenantRls();
    }

    public function down(): void
    {
        throw new RuntimeException('Program 11 privacy tenancy migration is forward-only.');
    }

    private function enableForcedTenantRls(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tenantScopedTables as $table) {
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

    private function assertTablesExist(): void
    {
        foreach ($this->tenantScopedTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                throw new RuntimeException("Program 11 privacy tenancy table is missing tenant scope: {$table}");
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(static fn (array $index): bool => (string) ($index['name'] ?? '') === $indexName);
    }

    private function identifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
};
