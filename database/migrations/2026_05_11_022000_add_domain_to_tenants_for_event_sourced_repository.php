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
        if (! Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'domain')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('domain')->nullable()->after('slug')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'domain')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            try {
                $table->dropIndex('tenants_domain_index');
            } catch (Throwable) {
                // The index may not exist in older/local schemas.
            }

            $table->dropColumn('domain');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS tenants_domain_index');
        }
    }
};
