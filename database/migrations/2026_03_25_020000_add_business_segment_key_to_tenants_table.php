<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('business_segment_key')
                ->nullable()
                ->after('business_type');

            $table->index('business_segment_key', 'tenants_business_segment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex('tenants_business_segment_idx');
            $table->dropColumn('business_segment_key');
        });
    }
};
