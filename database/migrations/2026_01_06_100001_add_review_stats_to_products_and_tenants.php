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
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'reviews_count')) {
                $table->unsignedInteger('reviews_count')->default(0);
            }
            if (! Schema::hasColumn('products', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->default(0);
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'reviews_count')) {
                $table->unsignedInteger('reviews_count')->default(0);
            }
            if (! Schema::hasColumn('tenants', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reviews_count', 'rating_avg']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['reviews_count', 'rating_avg']);
        });
    }
};
