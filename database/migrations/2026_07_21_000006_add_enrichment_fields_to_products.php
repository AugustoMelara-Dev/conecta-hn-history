<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'short_description')) {
                $table->text('short_description')->nullable();
            }
            if (! Schema::hasColumn('products', 'tags')) {
                $table->json('tags')->nullable();
            }
            if (! Schema::hasColumn('products', 'colors')) {
                $table->json('colors')->nullable();
            }
            if (! Schema::hasColumn('products', 'material')) {
                $table->string('material', 100)->nullable();
            }
            if (! Schema::hasColumn('products', 'dimensions')) {
                $table->json('dimensions')->nullable();
            }
            if (! Schema::hasColumn('products', 'condition')) {
                $table->string('condition', 30)->nullable();
            }
            if (! Schema::hasColumn('products', 'warnings')) {
                $table->json('warnings')->nullable();
            }
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'short_description',
            'tags',
            'colors',
            'material',
            'dimensions',
            'condition',
            'warnings',
            'unit',
        ], static fn (string $column): bool => Schema::hasColumn('products', $column)));

        if ($columns !== []) {
            Schema::table('products', static function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
