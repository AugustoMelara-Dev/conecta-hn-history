<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fixes redundancy between 'cover_image_path' (original) and 'cover_path' (later addition).
     * Standardizes on 'cover_image_path'.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Caso 1: Ambos existen, eliminamos el redundante 'cover_path'
            if (Schema::hasColumn('tenants', 'cover_image_path') && Schema::hasColumn('tenants', 'cover_path')) {
                $table->dropColumn('cover_path');
            }

            // Caso 2: Solo existe 'cover_path', lo renombramos a 'cover_image_path'
            elseif (! Schema::hasColumn('tenants', 'cover_image_path') && Schema::hasColumn('tenants', 'cover_path')) {
                $table->renameColumn('cover_path', 'cover_image_path');
            }

            // Caso 3 (Safety): Si ninguno existe, creamos el estándar
            elseif (! Schema::hasColumn('tenants', 'cover_image_path') && ! Schema::hasColumn('tenants', 'cover_path')) {
                $table->string('cover_image_path', 2048)->nullable()->after('banner_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed for down as this is a cleanup
    }
};
