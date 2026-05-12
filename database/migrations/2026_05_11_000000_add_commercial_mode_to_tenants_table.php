<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'commercial_mode')) {
                $table->string('commercial_mode', 40)
                    ->default('products')
                    ->after('business_type');
            }

            if (! Schema::hasColumn('tenants', 'rubro_key')) {
                $table->string('rubro_key', 80)
                    ->nullable()
                    ->after('commercial_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'rubro_key')) {
                $table->dropColumn('rubro_key');
            }

            if (Schema::hasColumn('tenants', 'commercial_mode')) {
                $table->dropColumn('commercial_mode');
            }
        });
    }
};
