<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('campaigns', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('scheduled_at');
            }

            if (! Schema::hasColumn('campaigns', 'cadence_interval_days')) {
                $table->unsignedInteger('cadence_interval_days')->nullable()->after('is_recurring');
            }

            if (! Schema::hasColumn('campaigns', 'next_run_at')) {
                $table->timestamp('next_run_at')->nullable()->after('cadence_interval_days');
            }

            if (! Schema::hasColumn('campaigns', 'last_dispatched_at')) {
                $table->timestamp('last_dispatched_at')->nullable()->after('next_run_at');
            }

            if (! Schema::hasColumn('campaigns', 'loyalty_reward_id')) {
                $table->foreignId('loyalty_reward_id')->nullable()->after('owner_user_id')->constrained('loyalty_rewards')->nullOnDelete();
            }

            $table->index(['status', 'scheduled_at'], 'campaigns_status_scheduled_at_idx');
            $table->index(['is_recurring', 'next_run_at'], 'campaigns_recurring_next_run_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            try {
                $table->dropIndex('campaigns_status_scheduled_at_idx');
            } catch (Throwable) {
            }

            try {
                $table->dropIndex('campaigns_recurring_next_run_at_idx');
            } catch (Throwable) {
            }

            if (Schema::hasColumn('campaigns', 'loyalty_reward_id')) {
                $table->dropConstrainedForeignId('loyalty_reward_id');
            }

            foreach (['last_dispatched_at', 'next_run_at', 'cadence_interval_days', 'is_recurring'] as $column) {
                if (Schema::hasColumn('campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
