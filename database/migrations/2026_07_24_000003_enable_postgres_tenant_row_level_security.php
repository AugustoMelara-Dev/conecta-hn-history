<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'orders',
        'order_items',
        'order_events',
        'appointments',
        'customer_profiles',
        'customer_notes',
        'customer_tasks',
        'crm_pipelines',
        'crm_stages',
        'crm_deals',
        'leads',
        'payments',
        'subscription_intents',
        'subscription_payments',
        'tenant_billing_references',
        'ai_executions',
        'ai_usage_logs',
        'ai_credit_grants',
        'ai_credit_ledger_entries',
        'ai_credit_reservations',
        'ai_credit_usage_alerts',
        'catalog_import_batches',
        'catalog_import_items',
        'campaigns',
        'interactions',
        'loyalty_ledger',
        'inventory_movements',
        'webhook_logs',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->eligibleTables() as $table) {
            $identifier = '"'.str_replace('"', '""', $table).'"';
            $policy = '"'.str_replace('"', '""', $table.'_tenant_isolation').'"';

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
            $identifier = '"'.str_replace('"', '""', $table).'"';
            $policy = '"'.str_replace('"', '""', $table.'_tenant_isolation').'"';

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
            static fn (string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id'),
        ));
    }
};
