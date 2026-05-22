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
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'tenant_id')) {
            return;
        }

        $orphanCount = DB::table('orders')->whereNull('tenant_id')->count();

        if ($orphanCount > 0) {
            throw new RuntimeException('Cannot make orders.tenant_id NOT NULL while orphan orders exist.');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'tenant_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->change();
        });
    }
};
