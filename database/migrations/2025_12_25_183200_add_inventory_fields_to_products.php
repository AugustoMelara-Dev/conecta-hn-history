<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Quantitative Inventory Management Fields to Products
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check and add each column separately to avoid conflicts
        if (! Schema::hasColumn('products', 'manage_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('manage_stock')
                    ->default(false)
                    ->after('is_locked');
            });
        }

        if (! Schema::hasColumn('products', 'stock_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('stock_quantity')
                    ->default(0)
                    ->after('is_locked');
            });
        }

        if (! Schema::hasColumn('products', 'allow_backorders')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('allow_backorders')
                    ->default(false)
                    ->after('is_locked');
            });
        }

        if (! Schema::hasColumn('products', 'stock_status')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('stock_status', 20)
                    ->default('in_stock')
                    ->after('is_locked');
            });
        }
    }

    public function down(): void
    {
        $columns = ['manage_stock', 'stock_quantity', 'allow_backorders', 'stock_status'];

        foreach ($columns as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
