<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interactions', function (Blueprint $table): void {
            $table->string('signal_class', 32)
                ->default('unverified_intent')
                ->after('type');
            $table->string('source_authority', 32)
                ->default('legacy_or_client')
                ->after('signal_class');
            $table->boolean('is_verified')
                ->default(false)
                ->after('source_authority');

            $table->index(
                ['tenant_id', 'is_verified', 'signal_class', 'created_at'],
                'interactions_tenant_trust_class_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table): void {
            $table->dropIndex('interactions_tenant_trust_class_created_idx');
            $table->dropColumn(['signal_class', 'source_authority', 'is_verified']);
        });
    }
};
