<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('blur_hash')->nullable()->after('image_path');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('blur_hash')->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('blur_hash');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('blur_hash');
        });
    }
};
