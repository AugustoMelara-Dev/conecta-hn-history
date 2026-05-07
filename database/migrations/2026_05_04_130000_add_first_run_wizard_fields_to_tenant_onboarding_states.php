<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_onboarding_states')) {
            return;
        }

        Schema::table('tenant_onboarding_states', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_step_key')) {
                $table->string('first_run_step_key', 120)->nullable()->after('first_run_dismissed_at');
            }

            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_completed_steps')) {
                $table->json('first_run_completed_steps')->nullable()->after('first_run_step_key');
            }

            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_skipped_reason')) {
                $table->string('first_run_skipped_reason', 120)->nullable()->after('first_run_completed_steps');
            }

            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_completed_at')) {
                $table->timestamp('first_run_completed_at')->nullable()->after('first_run_skipped_reason');
            }

            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_last_seen_at')) {
                $table->timestamp('first_run_last_seen_at')->nullable()->after('first_run_completed_at');
            }

            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_abandoned_at')) {
                $table->timestamp('first_run_abandoned_at')->nullable()->after('first_run_last_seen_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_onboarding_states')) {
            return;
        }

        Schema::table('tenant_onboarding_states', function (Blueprint $table): void {
            foreach ([
                'first_run_abandoned_at',
                'first_run_last_seen_at',
                'first_run_completed_at',
                'first_run_skipped_reason',
                'first_run_completed_steps',
                'first_run_step_key',
            ] as $column) {
                if (Schema::hasColumn('tenant_onboarding_states', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
