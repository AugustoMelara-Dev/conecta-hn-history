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
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('description');
            }
            if (! Schema::hasColumn('tenants', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }
            if (! Schema::hasColumn('tenants', 'tags')) {
                $table->json('tags')->nullable()->after('seo_description');
            }
            if (! Schema::hasColumn('tenants', 'category_suggestion')) {
                $table->string('category_suggestion')->nullable()->after('tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'tags', 'category_suggestion']);
        });
    }
};
