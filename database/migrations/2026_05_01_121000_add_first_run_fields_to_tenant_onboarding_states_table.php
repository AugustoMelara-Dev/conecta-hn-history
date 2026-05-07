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
            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_started_at')) {
                $table->timestamp('first_run_started_at')->nullable()->after('last_completed_at');
            }

            if (! Schema::hasColumn('tenant_onboarding_states', 'first_run_dismissed_at')) {
                $table->timestamp('first_run_dismissed_at')->nullable()->after('first_run_started_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_onboarding_states')) {
            return;
        }

        Schema::table('tenant_onboarding_states', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_onboarding_states', 'first_run_dismissed_at')) {
                $table->dropColumn('first_run_dismissed_at');
            }

            if (Schema::hasColumn('tenant_onboarding_states', 'first_run_started_at')) {
                $table->dropColumn('first_run_started_at');
            }
        });
    }
};
