<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Dashboard Compound Index to Orders Table
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PERFORMANCE OPTIMIZATION: O(log n) Dashboard Queries                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * INDEX STRATEGY (Leftmost Prefix Rule):
 * 1. tenant_id  - Scoping (always first for multi-tenant isolation)
 * 2. status     - Filtering (Kanban column: pending/processing/ready)
 * 3. created_at - Sorting (ORDER BY created_at DESC)
 *
 * QUERY OPTIMIZATION:
 * Before: Full table scan + filesort = O(n)
 * After:  B-Tree index seek + pre-sorted = O(log n)
 *
 * TARGET QUERIES:
 * - Dashboard Kanban: WHERE tenant_id=? AND status=? ORDER BY created_at DESC
 * - Order History: WHERE tenant_id=? ORDER BY created_at DESC
 * - Reporting: WHERE tenant_id=? AND created_at BETWEEN ? AND ?
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════
            // PRIMARY DASHBOARD INDEX
            // ═══════════════════════════════════════════════════════════════
            // Covers: tenant isolation + status filter + date sorting
            // Eliminates: filesort operations on high-volume tenants
            try {
                $table->index(
                    ['tenant_id', 'status', 'created_at'],
                    'orders_tenant_dashboard_index'
                );
            } catch (\Throwable) {
            }

            // ═══════════════════════════════════════════════════════════════
            // REPORTING INDEX
            // ═══════════════════════════════════════════════════════════════
            // Covers: date-range queries for sales reports
            // Used by: ReportController::salesExcel, ::salesPdf
            try {
                $table->index(
                    ['tenant_id', 'created_at'],
                    'orders_tenant_reporting_index'
                );
            } catch (\Throwable) {
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropIndex('orders_tenant_dashboard_index');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('orders_tenant_reporting_index');
            } catch (\Throwable) {
            }
        });
    }
};
