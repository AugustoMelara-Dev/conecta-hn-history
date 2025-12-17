<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas de perfil avanzado a tenants:
 * - simple_schedule: Horario texto simple para usuarios FREE
 * - latitude/longitude: Coordenadas GPS para PRO
 * - email, phone: Canales de contacto
 * - URLs de redes sociales
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Horario simple para usuarios FREE
            if (!Schema::hasColumn('tenants', 'simple_schedule')) {
                $table->string('simple_schedule')->nullable();
            }
            
            // Coordenadas GPS (PRO-only)
            if (!Schema::hasColumn('tenants', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('tenants', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            
            // Canales de contacto
            if (!Schema::hasColumn('tenants', 'email')) {
                $table->string('email')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'phone')) {
                $table->string('phone')->nullable();
            }
            
            // Redes sociales
            if (!Schema::hasColumn('tenants', 'facebook_url')) {
                $table->string('facebook_url')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'instagram_url')) {
                $table->string('instagram_url')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'tiktok_url')) {
                $table->string('tiktok_url')->nullable();
            }
            if (!Schema::hasColumn('tenants', 'website_url')) {
                $table->string('website_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = [
                'simple_schedule',
                'latitude',
                'longitude',
                'email',
                'phone',
                'facebook_url',
                'instagram_url',
                'tiktok_url',
                'website_url',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
