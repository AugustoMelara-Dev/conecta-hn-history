<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'category_id')) {
                $table->uuid('category_id')
                    ->nullable()
                    ->after('id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        // category_id is now part of the canonical base tenants schema.
    }
};
