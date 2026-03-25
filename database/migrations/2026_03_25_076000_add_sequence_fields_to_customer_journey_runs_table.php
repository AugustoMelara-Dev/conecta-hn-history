<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_journey_runs', function (Blueprint $table): void {
            $table->string('current_step_key', 120)->nullable()->after('status');
            $table->string('next_template_key', 120)->nullable()->after('current_step_key');
            $table->string('sequence_state', 32)->default('active')->after('next_template_key');
            $table->string('due_state', 32)->default('on_track')->after('sequence_state');
            $table->timestamp('step_due_at')->nullable()->after('next_action_at');

            $table->index(['tenant_id', 'sequence_state', 'due_state'], 'customer_journey_runs_tenant_sequence_due_idx');
            $table->index(['tenant_id', 'current_step_key'], 'customer_journey_runs_tenant_current_step_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_journey_runs', function (Blueprint $table): void {
            $table->dropIndex('customer_journey_runs_tenant_sequence_due_idx');
            $table->dropIndex('customer_journey_runs_tenant_current_step_idx');
            $table->dropColumn([
                'current_step_key',
                'next_template_key',
                'sequence_state',
                'due_state',
                'step_due_at',
            ]);
        });
    }
};
