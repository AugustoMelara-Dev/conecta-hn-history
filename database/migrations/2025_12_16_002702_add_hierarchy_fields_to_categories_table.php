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
        Schema::table('categories', function (Blueprint $table) {
            // Jerarquia padre-hijo (solo si no existe)
            if (! Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('slug')
                    ->constrained('categories')->nullOnDelete();
            }

            // Identidad visual (solo si no existen)
            if (! Schema::hasColumn('categories', 'color')) {
                $table->string('color', 7)->default('#3b82f6')->after('icon');
            }
            if (! Schema::hasColumn('categories', 'icon_image')) {
                $table->string('icon_image')->nullable()->after('color');
            }
            if (! Schema::hasColumn('categories', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('icon_image');
            }

            // Control de visualizacion (solo si no existen)
            if (! Schema::hasColumn('categories', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('categories', 'display_order')) {
                $table->unsignedInteger('display_order')->default(0)->after('is_featured');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('categories', 'icon_image')) {
                $table->dropColumn('icon_image');
            }
            if (Schema::hasColumn('categories', 'cover_image')) {
                $table->dropColumn('cover_image');
            }
            if (Schema::hasColumn('categories', 'is_featured')) {
                $table->dropColumn('is_featured');
            }
            if (Schema::hasColumn('categories', 'display_order')) {
                $table->dropColumn('display_order');
            }
        });
    }
};
