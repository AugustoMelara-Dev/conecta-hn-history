<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingAdminOwnerId = ! Schema::hasColumn('operational_playbook_tasks', 'admin_owner_id');
        $missingFollowUpStatus = ! Schema::hasColumn('operational_playbook_tasks', 'follow_up_status');
        $missingInternalNotes = ! Schema::hasColumn('operational_playbook_tasks', 'internal_notes');
        $missingNextFollowUpAt = ! Schema::hasColumn('operational_playbook_tasks', 'next_follow_up_at');
        $missingLastFollowedUpAt = ! Schema::hasColumn('operational_playbook_tasks', 'last_followed_up_at');

        if (! $missingAdminOwnerId && ! $missingFollowUpStatus && ! $missingInternalNotes && ! $missingNextFollowUpAt && ! $missingLastFollowedUpAt) {
            return;
        }

        Schema::table('operational_playbook_tasks', function (Blueprint $table) use (
            $missingAdminOwnerId,
            $missingFollowUpStatus,
            $missingInternalNotes,
            $missingNextFollowUpAt,
            $missingLastFollowedUpAt
        ): void {
            if ($missingAdminOwnerId) {
                $table->foreignUuid('admin_owner_id')
                    ->nullable()
                    ->after('assigned_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if ($missingFollowUpStatus) {
                $table->string('follow_up_status')
                    ->default('new')
                    ->after('status');
            }

            if ($missingInternalNotes) {
                $table->text('internal_notes')
                    ->nullable()
                    ->after('meta');
            }

            if ($missingNextFollowUpAt) {
                $table->timestamp('next_follow_up_at')
                    ->nullable()
                    ->after('due_at');
            }

            if ($missingLastFollowedUpAt) {
                $table->timestamp('last_followed_up_at')
                    ->nullable()
                    ->after($missingNextFollowUpAt ? 'next_follow_up_at' : 'due_at');
            }

            if ($missingAdminOwnerId && $missingFollowUpStatus && $missingNextFollowUpAt) {
                $table->index(['follow_up_status', 'queue'], 'opt_follow_up_status_queue_idx');
                $table->index(['admin_owner_id', 'follow_up_status'], 'opt_admin_owner_follow_up_idx');
                $table->index(['next_follow_up_at', 'follow_up_status'], 'opt_next_follow_up_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operational_playbook_tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('operational_playbook_tasks', 'next_follow_up_at')) {
                $table->dropIndex('opt_next_follow_up_idx');
            }

            if (Schema::hasColumn('operational_playbook_tasks', 'admin_owner_id')) {
                $table->dropIndex('opt_admin_owner_follow_up_idx');
                $table->dropConstrainedForeignId('admin_owner_id');
            }

            if (Schema::hasColumn('operational_playbook_tasks', 'follow_up_status')) {
                $table->dropIndex('opt_follow_up_status_queue_idx');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('operational_playbook_tasks', 'follow_up_status') ? 'follow_up_status' : null,
                Schema::hasColumn('operational_playbook_tasks', 'internal_notes') ? 'internal_notes' : null,
                Schema::hasColumn('operational_playbook_tasks', 'next_follow_up_at') ? 'next_follow_up_at' : null,
                Schema::hasColumn('operational_playbook_tasks', 'last_followed_up_at') ? 'last_followed_up_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
