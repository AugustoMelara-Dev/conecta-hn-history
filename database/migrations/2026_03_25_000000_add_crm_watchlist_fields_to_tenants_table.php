<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $watchScoreAnchor = Schema::hasColumn('tenants', 'crm_notes')
            ? 'crm_notes'
            : (Schema::hasColumn('tenants', 'crm_last_contacted_at') ? 'crm_last_contacted_at' : 'subscription_status');

        Schema::table('tenants', function (Blueprint $table) use ($watchScoreAnchor): void {
            if (! Schema::hasColumn('tenants', 'crm_watch_score')) {
                $table->unsignedSmallInteger('crm_watch_score')
                    ->default(0)
                    ->after($watchScoreAnchor);
            }

            if (! Schema::hasColumn('tenants', 'crm_watch_tone')) {
                $table->string('crm_watch_tone')
                    ->default('info')
                    ->after('crm_watch_score');
            }

            if (! Schema::hasColumn('tenants', 'crm_watch_source')) {
                $table->string('crm_watch_source')
                    ->nullable()
                    ->after('crm_watch_tone');
            }

            if (! Schema::hasColumn('tenants', 'crm_watch_metric')) {
                $table->string('crm_watch_metric')
                    ->nullable()
                    ->after('crm_watch_source');
            }

            if (! Schema::hasColumn('tenants', 'crm_watch_reason')) {
                $table->text('crm_watch_reason')
                    ->nullable()
                    ->after('crm_watch_metric');
            }

            if (! Schema::hasColumn('tenants', 'crm_watch_flagged_at')) {
                $table->timestamp('crm_watch_flagged_at')
                    ->nullable()
                    ->after('crm_watch_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('tenants', 'crm_watch_score') ? 'crm_watch_score' : null,
                Schema::hasColumn('tenants', 'crm_watch_tone') ? 'crm_watch_tone' : null,
                Schema::hasColumn('tenants', 'crm_watch_source') ? 'crm_watch_source' : null,
                Schema::hasColumn('tenants', 'crm_watch_metric') ? 'crm_watch_metric' : null,
                Schema::hasColumn('tenants', 'crm_watch_reason') ? 'crm_watch_reason' : null,
                Schema::hasColumn('tenants', 'crm_watch_flagged_at') ? 'crm_watch_flagged_at' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
