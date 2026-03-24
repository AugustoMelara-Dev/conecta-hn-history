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
            $table->unsignedSmallInteger('crm_watch_score')
                ->default(0)
                ->after('crm_notes');

            $table->string('crm_watch_tone')
                ->default('info')
                ->after('crm_watch_score');

            $table->string('crm_watch_source')
                ->nullable()
                ->after('crm_watch_tone');

            $table->string('crm_watch_metric')
                ->nullable()
                ->after('crm_watch_source');

            $table->text('crm_watch_reason')
                ->nullable()
                ->after('crm_watch_metric');

            $table->timestamp('crm_watch_flagged_at')
                ->nullable()
                ->after('crm_watch_reason');

            $table->index(['crm_watch_score', 'crm_status'], 'tenants_crm_watch_score_idx');
            $table->index(['crm_watch_flagged_at', 'crm_watch_score'], 'tenants_crm_watch_flagged_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex('tenants_crm_watch_score_idx');
            $table->dropIndex('tenants_crm_watch_flagged_idx');
            $table->dropColumn([
                'crm_watch_score',
                'crm_watch_tone',
                'crm_watch_source',
                'crm_watch_metric',
                'crm_watch_reason',
                'crm_watch_flagged_at',
            ]);
        });
    }
};
