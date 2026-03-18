<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'city_id')) {
                $table->uuid('city_id')
                    ->nullable()
                    ->after('category_id')
                    ->index();
            }

            if (! Schema::hasColumn('tenants', 'status')) {
                $table->string('status')->default('pending')->after('is_active');
            }

            if (! Schema::hasColumn('tenants', 'is_pro')) {
                $table->boolean('is_pro')->default(false)->after('status');
            }

            if (! Schema::hasColumn('tenants', 'product_limit')) {
                $table->unsignedInteger('product_limit')->default(10)->after('is_pro');
            }

            if (! Schema::hasColumn('tenants', 'banner_path')) {
                $table->string('banner_path', 2048)->nullable()->after('product_limit');
            }

            if (! Schema::hasColumn('tenants', 'description')) {
                $table->text('description')->nullable()->after('banner_path');
            }

            if (! Schema::hasColumn('tenants', 'social_links')) {
                $table->json('social_links')->nullable()->after('description');
            }

            if (! Schema::hasColumn('tenants', 'primary_color')) {
                $table->string('primary_color', 7)->nullable()->after('social_links');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = ['status', 'is_pro', 'product_limit', 'banner_path', 'social_links', 'primary_color'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
