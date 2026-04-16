<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('locations') || Schema::hasColumn('orders', 'location_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'location_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropForeign(['location_id']);
            } catch (\Throwable) {
            }
            $table->dropColumn('location_id');
        });
    }
};
