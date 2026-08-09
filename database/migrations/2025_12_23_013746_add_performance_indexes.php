<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Indexes Migration - Zero-Downtime Strategy
 *
 * OPTIMIZATION STRATEGY:
 * These compound indexes follow the "Leftmost Prefix Rule" for B-Tree indexes.
 * The column order matters: tenant_id comes first because almost ALL queries
 * in a multi-tenant app are scoped by tenant.
 *
 * ZERO-DOWNTIME DDL (MySQL/MariaDB):
 * Uses ALGORITHM=INPLACE and LOCK=NONE to allow concurrent reads/writes
 * during index creation. This prevents blocking production traffic.
 *
 * POLYMORPHIC EXECUTION:
 * - MySQL/MariaDB: Uses raw SQL with non-blocking directives
 * - SQLite (Testing): Falls back to standard Schema::table
 *
 * EXPECTED IMPACT:
 * - Dashboard queries: ~100x faster (O(N) → O(log N))
 * - Product catalog: Index-only scans for visibility checks
 * - Analytics: Efficient date range queries per tenant
 * - Permission checks: Instant tenant_user lookups
 */
return new class extends Migration
{
    /**
     * Index definitions for polymorphic execution.
     */
    private array $indexes = [
        // [table, columns[], name, unique?]
        ['orders', ['tenant_id', 'status', 'created_at'], 'idx_orders_tenant_status_created', false],
        ['products', ['tenant_id', 'is_visible', 'category_id'], 'idx_products_tenant_visible_category', false],
        ['products', ['tenant_id', 'menu_section_id', 'is_visible'], 'idx_products_tenant_section_visible', false],
        ['interactions', ['tenant_id', 'created_at'], 'idx_interactions_tenant_created', false],
        ['interactions', ['tenant_id', 'type', 'created_at'], 'idx_interactions_tenant_type_created', false],
        ['tenant_users', ['tenant_id', 'user_id'], 'idx_tenant_users_lookup', true],
        ['tenant_visits', ['tenant_id', 'session_hash'], 'idx_tenant_visits_lookup', false],
        ['tenant_visits', ['tenant_id', 'created_at'], 'idx_tenant_visits_tenant_date', false],
        ['leads', ['tenant_id', 'status', 'created_at'], 'idx_leads_tenant_status_created', false],
        ['coupons', ['tenant_id', 'code'], 'idx_coupons_tenant_code', true],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach ($this->indexes as [$table, $columns, $name, $unique]) {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                // ═══════════════════════════════════════════════════════════════════
                // MYSQL/MARIADB: Zero-Downtime DDL with ALGORITHM=INPLACE, LOCK=NONE
                // This allows concurrent reads/writes during index creation
                // ═══════════════════════════════════════════════════════════════════
                $this->createIndexMySQL($table, $columns, $name, $unique);
            } else {
                // ═══════════════════════════════════════════════════════════════════
                // SQLITE/OTHERS: Standard Schema Builder (for testing environments)
                // ═══════════════════════════════════════════════════════════════════
                $this->createIndexStandard($table, $columns, $name, $unique);
            }
        }
    }

    /**
     * Create index using MySQL-specific zero-downtime DDL.
     */
    private function createIndexMySQL(string $table, array $columns, string $name, bool $unique): void
    {
        // Check if index already exists (idempotency)
        $exists = DB::select('
            SELECT COUNT(*) as cnt 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND INDEX_NAME = ?
        ', [$table, $name]);

        if (($exists[0]->cnt ?? 0) > 0) {
            // Index already exists - skip (idempotent)
            return;
        }

        $columnList = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';

        // Execute zero-downtime DDL
        // ALGORITHM=INPLACE: Modifies table in-place without full table copy
        // LOCK=NONE: Allows concurrent DML (INSERT, UPDATE, DELETE)
        $sql = "CREATE {$indexType} `{$name}` ON `{$table}` ({$columnList}) ALGORITHM=INPLACE LOCK=NONE";

        try {
            DB::statement($sql);
        } catch (Exception $e) {
            // Fallback: Some index types don't support INPLACE (e.g., FULLTEXT)
            // Try without the algorithm hints
            $fallbackSql = "CREATE {$indexType} `{$name}` ON `{$table}` ({$columnList})";
            DB::statement($fallbackSql);
        }
    }

    /**
     * Create index using standard Schema Builder (SQLite compatible).
     */
    private function createIndexStandard(string $table, array $columns, string $name, bool $unique): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name, $unique) {
            if ($unique) {
                $blueprint->unique($columns, $name);
            } else {
                $blueprint->index($columns, $name);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach ($this->indexes as [$table, $columns, $name, $unique]) {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $this->dropIndexMySQL($table, $name);
            } else {
                $this->dropIndexStandard($table, $name, $unique);
            }
        }
    }

    /**
     * Drop index using MySQL-specific DDL.
     */
    private function dropIndexMySQL(string $table, string $name): void
    {
        // Check if index exists before dropping
        $exists = DB::select('
            SELECT COUNT(*) as cnt 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND INDEX_NAME = ?
        ', [$table, $name]);

        if (($exists[0]->cnt ?? 0) > 0) {
            DB::statement("DROP INDEX `{$name}` ON `{$table}` ALGORITHM=INPLACE LOCK=NONE");
        }
    }

    /**
     * Drop index using standard Schema Builder.
     */
    private function dropIndexStandard(string $table, string $name, bool $unique): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($name, $unique) {
            if ($unique) {
                $blueprint->dropUnique($name);
            } else {
                $blueprint->dropIndex($name);
            }
        });
    }
};
