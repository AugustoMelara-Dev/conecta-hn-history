<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_playbook_tasks', function (Blueprint $table): void {
            $table->foreignUuid('admin_owner_id')
                ->nullable()
                ->after('assigned_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('follow_up_status')
                ->default('new')
                ->after('status');

            $table->text('internal_notes')
                ->nullable()
                ->after('meta');

            $table->timestamp('next_follow_up_at')
                ->nullable()
                ->after('due_at');

            $table->timestamp('last_followed_up_at')
                ->nullable()
                ->after('next_follow_up_at');

            $table->index(['follow_up_status', 'queue'], 'opt_follow_up_status_queue_idx');
            $table->index(['admin_owner_id', 'follow_up_status'], 'opt_admin_owner_follow_up_idx');
            $table->index(['next_follow_up_at', 'follow_up_status'], 'opt_next_follow_up_idx');
        });
    }

    public function down(): void
    {
        Schema::table('operational_playbook_tasks', function (Blueprint $table): void {
            $table->dropIndex('opt_next_follow_up_idx');
            $table->dropIndex('opt_admin_owner_follow_up_idx');
            $table->dropIndex('opt_follow_up_status_queue_idx');
            $table->dropConstrainedForeignId('admin_owner_id');
            $table->dropColumn([
                'follow_up_status',
                'internal_notes',
                'next_follow_up_at',
                'last_followed_up_at',
            ]);
        });
    }
};
