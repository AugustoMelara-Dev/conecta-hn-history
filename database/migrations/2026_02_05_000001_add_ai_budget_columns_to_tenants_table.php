<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'ai_balance')) {
                $table->decimal('ai_balance', 10, 6)
                    ->default(0.000000)
                    ->after('plan')
                    ->comment('Creditos IA confirmados disponibles (USD)');
            }

            if (! Schema::hasColumn('tenants', 'ai_reserved_balance')) {
                $table->decimal('ai_reserved_balance', 10, 6)
                    ->default(0.000000)
                    ->after('ai_balance')
                    ->comment('Creditos IA bloqueados en solicitudes pendientes (USD)');
            }

            if (
                Schema::hasColumn('tenants', 'ai_balance')
                && Schema::hasColumn('tenants', 'ai_reserved_balance')
                && ! Schema::hasIndex('tenants', 'idx_tenants_ai_budget')
            ) {
                $table->index(['ai_balance', 'ai_reserved_balance'], 'idx_tenants_ai_budget');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasIndex('tenants', 'idx_tenants_ai_budget')) {
                $table->dropIndex('idx_tenants_ai_budget');
            }
        });
    }
};
