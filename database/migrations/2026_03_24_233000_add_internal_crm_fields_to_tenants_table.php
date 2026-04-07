<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingCrmOwnerId = ! Schema::hasColumn('tenants', 'crm_owner_id');
        $missingCrmTeam = ! Schema::hasColumn('tenants', 'crm_team');
        $missingCrmStatus = ! Schema::hasColumn('tenants', 'crm_status');
        $missingCrmPriority = ! Schema::hasColumn('tenants', 'crm_priority');
        $missingCrmSlaHours = ! Schema::hasColumn('tenants', 'crm_sla_hours');
        $missingCrmNextFollowUpAt = ! Schema::hasColumn('tenants', 'crm_next_follow_up_at');
        $missingCrmLastContactedAt = ! Schema::hasColumn('tenants', 'crm_last_contacted_at');
        $missingCrmNotes = ! Schema::hasColumn('tenants', 'crm_notes');

        if (
            ! $missingCrmOwnerId
            && ! $missingCrmTeam
            && ! $missingCrmStatus
            && ! $missingCrmPriority
            && ! $missingCrmSlaHours
            && ! $missingCrmNextFollowUpAt
            && ! $missingCrmLastContactedAt
            && ! $missingCrmNotes
        ) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) use (
            $missingCrmOwnerId,
            $missingCrmTeam,
            $missingCrmStatus,
            $missingCrmPriority,
            $missingCrmSlaHours,
            $missingCrmNextFollowUpAt,
            $missingCrmLastContactedAt,
            $missingCrmNotes
        ): void {
            if ($missingCrmOwnerId) {
                $table->foreignUuid('crm_owner_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if ($missingCrmTeam) {
                $table->string('crm_team')
                    ->default('activation')
                    ->after('subscription_status');
            }

            if ($missingCrmStatus) {
                $table->string('crm_status')
                    ->default('new')
                    ->after($missingCrmTeam ? 'crm_team' : 'subscription_status');
            }

            if ($missingCrmPriority) {
                $table->string('crm_priority')
                    ->default('normal')
                    ->after($missingCrmStatus ? 'crm_status' : ($missingCrmTeam ? 'crm_team' : 'subscription_status'));
            }

            if ($missingCrmSlaHours) {
                $table->unsignedSmallInteger('crm_sla_hours')
                    ->default(24)
                    ->after($missingCrmPriority ? 'crm_priority' : ($missingCrmStatus ? 'crm_status' : 'subscription_status'));
            }

            if ($missingCrmNextFollowUpAt) {
                $table->timestamp('crm_next_follow_up_at')
                    ->nullable()
                    ->after($missingCrmSlaHours ? 'crm_sla_hours' : 'subscription_status');
            }

            if ($missingCrmLastContactedAt) {
                $table->timestamp('crm_last_contacted_at')
                    ->nullable()
                    ->after($missingCrmNextFollowUpAt ? 'crm_next_follow_up_at' : 'subscription_status');
            }

            if ($missingCrmNotes) {
                $table->text('crm_notes')
                    ->nullable()
                    ->after($missingCrmLastContactedAt ? 'crm_last_contacted_at' : 'subscription_status');
            }

            if (
                $missingCrmOwnerId
                && $missingCrmTeam
                && $missingCrmStatus
                && $missingCrmNextFollowUpAt
            ) {
                $table->index(['crm_owner_id', 'crm_status'], 'tenants_crm_owner_status_idx');
                $table->index(['crm_team', 'crm_status'], 'tenants_crm_team_status_idx');
                $table->index(['crm_next_follow_up_at', 'crm_status'], 'tenants_crm_next_touch_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'crm_next_follow_up_at')) {
                $table->dropIndex('tenants_crm_next_touch_idx');
            }

            if (Schema::hasColumn('tenants', 'crm_team')) {
                $table->dropIndex('tenants_crm_team_status_idx');
            }

            if (Schema::hasColumn('tenants', 'crm_owner_id')) {
                $table->dropIndex('tenants_crm_owner_status_idx');
                $table->dropConstrainedForeignId('crm_owner_id');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('tenants', 'crm_team') ? 'crm_team' : null,
                Schema::hasColumn('tenants', 'crm_status') ? 'crm_status' : null,
                Schema::hasColumn('tenants', 'crm_priority') ? 'crm_priority' : null,
                Schema::hasColumn('tenants', 'crm_sla_hours') ? 'crm_sla_hours' : null,
                Schema::hasColumn('tenants', 'crm_next_follow_up_at') ? 'crm_next_follow_up_at' : null,
                Schema::hasColumn('tenants', 'crm_last_contacted_at') ? 'crm_last_contacted_at' : null,
                Schema::hasColumn('tenants', 'crm_notes') ? 'crm_notes' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
