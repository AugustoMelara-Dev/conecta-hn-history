<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'status')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('status', 32)->default('approved')->after('slug');
            $table->index('status', 'tenants_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'status')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS tenants_status_idx');
        }

        Schema::table('tenants', function (Blueprint $table): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropIndex('tenants_status_idx');
            }

            $table->dropColumn('status');
        });
    }
};
