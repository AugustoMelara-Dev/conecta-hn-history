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
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'color')) {
                $table->string('color')->nullable()->default('#000000')->after('slug');
            }
            if (! Schema::hasColumn('categories', 'icon')) {
                $table->string('icon')->nullable()->after('color');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('categories', 'color')) {
                $columns[] = 'color';
            }
            if (Schema::hasColumn('categories', 'icon')) {
                $columns[] = 'icon';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
