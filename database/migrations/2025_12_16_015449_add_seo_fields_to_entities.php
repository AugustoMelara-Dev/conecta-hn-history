<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campos SEO estandarizados a múltiples entidades.
 *
 * Columnas:
 * - seo_title: Título optimizado para Google (60 chars recomendado)
 * - seo_description: Meta descripción (160 chars recomendado)
 * - seo_keywords: Palabras clave JSON
 * - og_image: Imagen Open Graph para redes sociales (1200x630px)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // CITIES: Campos SEO para páginas de ciudades
        // ═══════════════════════════════════════════════════════════════════
        if (Schema::hasTable('cities')) {
            Schema::table('cities', function (Blueprint $table) {
                if (! Schema::hasColumn('cities', 'seo_title')) {
                    $table->string('seo_title', 70)->nullable()->after('meta_description');
                }
                if (! Schema::hasColumn('cities', 'seo_description')) {
                    $table->string('seo_description', 170)->nullable()->after('seo_title');
                }
                if (! Schema::hasColumn('cities', 'seo_keywords')) {
                    $table->json('seo_keywords')->nullable()->after('seo_description');
                }
                if (! Schema::hasColumn('cities', 'og_image')) {
                    $table->string('og_image')->nullable()->after('seo_keywords');
                }
            });
        }

        // ═══════════════════════════════════════════════════════════════════
        // CATEGORIES: Campos SEO para páginas de categorías
        // ═══════════════════════════════════════════════════════════════════
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'seo_title')) {
                    $table->string('seo_title', 70)->nullable()->after('cover_image');
                }
                if (! Schema::hasColumn('categories', 'seo_description')) {
                    $table->string('seo_description', 170)->nullable()->after('seo_title');
                }
                if (! Schema::hasColumn('categories', 'seo_keywords')) {
                    $table->json('seo_keywords')->nullable()->after('seo_description');
                }
                if (! Schema::hasColumn('categories', 'og_image')) {
                    $table->string('og_image')->nullable()->after('seo_keywords');
                }
            });
        }

        // ═══════════════════════════════════════════════════════════════════
        // TENANTS: Campos SEO para micrositios de negocios
        // ═══════════════════════════════════════════════════════════════════
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (! Schema::hasColumn('tenants', 'seo_title')) {
                    $table->string('seo_title', 70)->nullable()->after('slug');
                }
                if (! Schema::hasColumn('tenants', 'seo_description')) {
                    $table->string('seo_description', 170)->nullable()->after('seo_title');
                }
                if (! Schema::hasColumn('tenants', 'seo_keywords')) {
                    $table->json('seo_keywords')->nullable()->after('seo_description');
                }
                if (! Schema::hasColumn('tenants', 'og_image')) {
                    $table->string('og_image')->nullable()->after('seo_keywords');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['cities', 'categories', 'tenants'];
        $columns = ['seo_title', 'seo_description', 'seo_keywords', 'og_image'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
