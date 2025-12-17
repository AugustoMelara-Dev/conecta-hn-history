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
            // Campo de ordenamiento para merchandising
            if (! Schema::hasColumn('categories', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('icon');
            }

            // Campo para icono SVG personalizado
            if (! Schema::hasColumn('categories', 'icon_svg')) {
                $table->string('icon_svg')->nullable()->after('icon');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('categories', 'icon_svg')) {
                $table->dropColumn('icon_svg');
            }
        });
    }
};
