<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignUuid('crm_owner_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('crm_team')
                ->default('activation')
                ->after('subscription_status');

            $table->string('crm_status')
                ->default('new')
                ->after('crm_team');

            $table->string('crm_priority')
                ->default('normal')
                ->after('crm_status');

            $table->unsignedSmallInteger('crm_sla_hours')
                ->default(24)
                ->after('crm_priority');

            $table->timestamp('crm_next_follow_up_at')
                ->nullable()
                ->after('crm_sla_hours');

            $table->timestamp('crm_last_contacted_at')
                ->nullable()
                ->after('crm_next_follow_up_at');

            $table->text('crm_notes')
                ->nullable()
                ->after('crm_last_contacted_at');

            $table->index(['crm_owner_id', 'crm_status'], 'tenants_crm_owner_status_idx');
            $table->index(['crm_team', 'crm_status'], 'tenants_crm_team_status_idx');
            $table->index(['crm_next_follow_up_at', 'crm_status'], 'tenants_crm_next_touch_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex('tenants_crm_next_touch_idx');
            $table->dropIndex('tenants_crm_team_status_idx');
            $table->dropIndex('tenants_crm_owner_status_idx');

            $table->dropConstrainedForeignId('crm_owner_id');
            $table->dropColumn([
                'crm_team',
                'crm_status',
                'crm_priority',
                'crm_sla_hours',
                'crm_next_follow_up_at',
                'crm_last_contacted_at',
                'crm_notes',
            ]);
        });
    }
};
