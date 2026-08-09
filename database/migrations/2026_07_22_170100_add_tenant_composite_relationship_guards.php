<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private const RELATIONSHIPS = [
        ['catalog_import_items', 'batch_id', 'catalog_import_batches', 'id'],
        ['catalog_import_items', 'duplicate_of_id', 'catalog_import_items', 'id'],
        ['catalog_import_items', 'product_id', 'products', 'id'],
        ['appointments', 'service_id', 'services', 'id'],
        ['appointments', 'service_provider_id', 'service_providers', 'id'],
        ['appointments', 'customer_profile_id', 'customer_profiles', 'id'],
        ['orders', 'customer_profile_id', 'customer_profiles', 'id'],
        ['leads', 'customer_profile_id', 'customer_profiles', 'id'],
        ['customer_notes', 'customer_profile_id', 'customer_profiles', 'id'],
        ['customer_tasks', 'customer_profile_id', 'customer_profiles', 'id'],
        ['crm_deals', 'customer_profile_id', 'customer_profiles', 'id'],
    ];

    public function up(): void
    {
        $this->assertNoCrossTenantRelationships();

        $this->addParentKeys();
        $this->addRelationshipGuards();
    }

    public function down(): void
    {
        $this->dropRelationshipGuards();
        $this->dropParentKeys();
    }

    private function assertNoCrossTenantRelationships(): void
    {
        foreach (self::RELATIONSHIPS as [$childTable, $childColumn, $parentTable, $parentColumn]) {
            $childAlias = 'guard_child';
            $parentAlias = 'guard_parent';
            $invalidRows = DB::table("{$childTable} as {$childAlias}")
                ->join(
                    "{$parentTable} as {$parentAlias}",
                    "{$parentAlias}.{$parentColumn}",
                    '=',
                    "{$childAlias}.{$childColumn}",
                )
                ->whereNotNull("{$childAlias}.{$childColumn}")
                ->whereColumn("{$childAlias}.tenant_id", '<>', "{$parentAlias}.tenant_id")
                ->count();

            if ($invalidRows > 0) {
                throw new \RuntimeException(
                    "Cannot add tenant relationship guard for {$childTable}.{$childColumn}: {$invalidRows} cross-tenant row(s) exist.",
                );
            }
        }

        $invalidLoyaltyRows = DB::table('loyalty_ledger as guard_child')
            ->join('orders as guard_parent', 'guard_parent.id', '=', 'guard_child.order_id')
            ->whereNotNull('guard_child.order_id')
            ->where(function ($query): void {
                $query->whereColumn('guard_child.tenant_id', '<>', 'guard_parent.tenant_id')
                    ->orWhereColumn('guard_child.user_id', '<>', 'guard_parent.user_id');
            })
            ->count();

        if ($invalidLoyaltyRows > 0) {
            throw new \RuntimeException(
                "Cannot add tenant relationship guard for loyalty_ledger.order_id: {$invalidLoyaltyRows} incompatible row(s) exist.",
            );
        }
    }

    private function addParentKeys(): void
    {
        $keys = [
            ['catalog_import_batches', ['tenant_id', 'id'], 'cib_tenant_id_id_unique'],
            ['catalog_import_items', ['tenant_id', 'id'], 'cii_tenant_id_id_unique'],
            ['products', ['tenant_id', 'id'], 'products_tenant_id_id_unique'],
            ['services', ['tenant_id', 'id'], 'services_tenant_id_id_unique'],
            ['service_providers', ['tenant_id', 'id'], 'sp_tenant_id_id_unique'],
            ['customer_profiles', ['tenant_id', 'id'], 'cp_tenant_id_id_unique'],
            ['orders', ['tenant_id', 'id'], 'orders_tenant_id_id_unique'],
            ['orders', ['tenant_id', 'id', 'user_id'], 'orders_tenant_id_user_id_unique'],
        ];

        foreach ($keys as [$tableName, $columns, $name]) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $name): void {
                $table->unique($columns, $name);
            });
        }
    }

    private function addRelationshipGuards(): void
    {
        $guards = [
            ['catalog_import_items', ['tenant_id', 'batch_id'], 'catalog_import_batches', ['tenant_id', 'id'], 'cii_batch_tenant_fk', 'cascade'],
            ['catalog_import_items', ['tenant_id', 'duplicate_of_id'], 'catalog_import_items', ['tenant_id', 'id'], 'cii_duplicate_tenant_fk', 'null'],
            ['catalog_import_items', ['tenant_id', 'product_id'], 'products', ['tenant_id', 'id'], 'cii_product_tenant_fk', 'null'],
            ['appointments', ['tenant_id', 'service_id'], 'services', ['tenant_id', 'id'], 'appointments_service_tenant_fk', 'cascade'],
            ['appointments', ['tenant_id', 'service_provider_id'], 'service_providers', ['tenant_id', 'id'], 'appointments_provider_tenant_fk', 'cascade'],
            ['appointments', ['tenant_id', 'customer_profile_id'], 'customer_profiles', ['tenant_id', 'id'], 'appointments_profile_tenant_fk', 'null'],
            ['orders', ['tenant_id', 'customer_profile_id'], 'customer_profiles', ['tenant_id', 'id'], 'orders_profile_tenant_fk', 'null'],
            ['leads', ['tenant_id', 'customer_profile_id'], 'customer_profiles', ['tenant_id', 'id'], 'leads_profile_tenant_fk', 'null'],
            ['customer_notes', ['tenant_id', 'customer_profile_id'], 'customer_profiles', ['tenant_id', 'id'], 'notes_profile_tenant_fk', 'cascade'],
            ['customer_tasks', ['tenant_id', 'customer_profile_id'], 'customer_profiles', ['tenant_id', 'id'], 'tasks_profile_tenant_fk', 'cascade'],
            ['crm_deals', ['tenant_id', 'customer_profile_id'], 'customer_profiles', ['tenant_id', 'id'], 'deals_profile_tenant_fk', 'null'],
            ['loyalty_ledger', ['tenant_id', 'order_id', 'user_id'], 'orders', ['tenant_id', 'id', 'user_id'], 'loyalty_order_tenant_user_fk', 'null'],
        ];

        foreach ($guards as [$tableName, $columns, $parent, $parentColumns, $name, $onDelete]) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $parent, $parentColumns, $name, $onDelete): void {
                $foreign = $table->foreign($columns, $name)->references($parentColumns)->on($parent);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                }
            });
        }
    }

    private function dropRelationshipGuards(): void
    {
        $guards = [
            ['catalog_import_items', 'cii_batch_tenant_fk'],
            ['catalog_import_items', 'cii_duplicate_tenant_fk'],
            ['catalog_import_items', 'cii_product_tenant_fk'],
            ['appointments', 'appointments_service_tenant_fk'],
            ['appointments', 'appointments_provider_tenant_fk'],
            ['appointments', 'appointments_profile_tenant_fk'],
            ['orders', 'orders_profile_tenant_fk'],
            ['leads', 'leads_profile_tenant_fk'],
            ['customer_notes', 'notes_profile_tenant_fk'],
            ['customer_tasks', 'tasks_profile_tenant_fk'],
            ['crm_deals', 'deals_profile_tenant_fk'],
            ['loyalty_ledger', 'loyalty_order_tenant_user_fk'],
        ];

        foreach ($guards as [$tableName, $name]) {
            Schema::table($tableName, function (Blueprint $table) use ($name): void {
                $table->dropForeign($name);
            });
        }
    }

    private function dropParentKeys(): void
    {
        $keys = [
            ['orders', 'orders_tenant_id_user_id_unique'],
            ['orders', 'orders_tenant_id_id_unique'],
            ['customer_profiles', 'cp_tenant_id_id_unique'],
            ['service_providers', 'sp_tenant_id_id_unique'],
            ['services', 'services_tenant_id_id_unique'],
            ['products', 'products_tenant_id_id_unique'],
            ['catalog_import_items', 'cii_tenant_id_id_unique'],
            ['catalog_import_batches', 'cib_tenant_id_id_unique'],
        ];

        foreach ($keys as [$tableName, $name]) {
            Schema::table($tableName, function (Blueprint $table) use ($name): void {
                $table->dropUnique($name);
            });
        }
    }
};
