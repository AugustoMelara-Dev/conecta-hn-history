<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('leads', 'session_id')) {
                $table->string('session_id', 100)->nullable()->after('source');
                $table->index(['tenant_id', 'session_id'], 'leads_tenant_session_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasColumn('leads', 'session_id')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_tenant_session_idx');
            $table->dropColumn('session_id');
        });
    }
};
