<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            // SEO y Landing Page (solo si no existen)
            if (! Schema::hasColumn('cities', 'department')) {
                $table->string('department')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('cities', 'hero_image')) {
                $table->string('hero_image')->nullable()->after('department');
            }
            if (! Schema::hasColumn('cities', 'welcome_text')) {
                $table->text('welcome_text')->nullable()->after('hero_image');
            }
            if (! Schema::hasColumn('cities', 'meta_title')) {
                $table->string('meta_title', 100)->nullable()->after('welcome_text');
            }
            if (! Schema::hasColumn('cities', 'meta_description')) {
                $table->string('meta_description', 200)->nullable()->after('meta_title');
            }

            // Datos demograficos (solo si no existe)
            if (! Schema::hasColumn('cities', 'population')) {
                $table->unsignedInteger('population')->nullable()->after('meta_description');
            }

            // Control de expansion (solo si no existen)
            if (! Schema::hasColumn('cities', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_available');
            }
            if (! Schema::hasColumn('cities', 'launch_order')) {
                $table->unsignedInteger('launch_order')->default(0)->after('is_featured');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $columns = ['department', 'hero_image', 'welcome_text', 'meta_title', 'meta_description', 'population', 'is_featured', 'launch_order'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('cities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
