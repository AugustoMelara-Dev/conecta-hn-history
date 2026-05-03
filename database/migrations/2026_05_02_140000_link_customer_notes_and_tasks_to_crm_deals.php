<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_notes') && Schema::hasTable('crm_deals') && ! Schema::hasColumn('customer_notes', 'crm_deal_id')) {
            Schema::table('customer_notes', function (Blueprint $table): void {
                $table->foreignId('crm_deal_id')
                    ->nullable()
                    ->after('customer_profile_id')
                    ->constrained('crm_deals')
                    ->nullOnDelete();
                $table->index(['tenant_id', 'crm_deal_id', 'created_at'], 'customer_notes_tenant_deal_created_idx');
            });
        }

        if (Schema::hasTable('customer_tasks') && Schema::hasTable('crm_deals') && ! Schema::hasColumn('customer_tasks', 'crm_deal_id')) {
            Schema::table('customer_tasks', function (Blueprint $table): void {
                $table->foreignId('crm_deal_id')
                    ->nullable()
                    ->after('customer_profile_id')
                    ->constrained('crm_deals')
                    ->nullOnDelete();
                $table->index(['tenant_id', 'crm_deal_id', 'status'], 'customer_tasks_tenant_deal_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_tasks') && Schema::hasColumn('customer_tasks', 'crm_deal_id')) {
            Schema::table('customer_tasks', function (Blueprint $table): void {
                $table->dropIndex('customer_tasks_tenant_deal_status_idx');
                $table->dropForeign(['crm_deal_id']);
                $table->dropColumn('crm_deal_id');
            });
        }

        if (Schema::hasTable('customer_notes') && Schema::hasColumn('customer_notes', 'crm_deal_id')) {
            Schema::table('customer_notes', function (Blueprint $table): void {
                $table->dropIndex('customer_notes_tenant_deal_created_idx');
                $table->dropForeign(['crm_deal_id']);
                $table->dropColumn('crm_deal_id');
            });
        }
    }
};
