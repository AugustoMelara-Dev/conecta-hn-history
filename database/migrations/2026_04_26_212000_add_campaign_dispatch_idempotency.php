<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campaign_dispatch_runs')) {
            Schema::create('campaign_dispatch_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
                $table->string('run_key', 96);
                $table->string('status', 32)->default('running');
                $table->unsignedBigInteger('cursor_profile_id')->nullable();
                $table->unsignedInteger('queued_count')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'campaign_id', 'run_key'], 'campaign_dispatch_runs_unique');
                $table->index(['status', 'campaign_id'], 'campaign_dispatch_runs_status_idx');
            });
        }

        if (! Schema::hasTable('customer_touchpoints')) {
            return;
        }

        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_touchpoints', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('customer_profile_id')->constrained('campaigns')->nullOnDelete();
            }

            if (! Schema::hasColumn('customer_touchpoints', 'campaign_run_key')) {
                $table->string('campaign_run_key', 96)->nullable()->after('campaign_id');
            }

            $table->unique(
                ['tenant_id', 'campaign_id', 'campaign_run_key', 'customer_profile_id'],
                'customer_touchpoints_campaign_run_unique'
            );
            $table->index(
                ['tenant_id', 'campaign_id', 'campaign_run_key'],
                'customer_touchpoints_campaign_run_idx'
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_touchpoints')) {
            Schema::table('customer_touchpoints', function (Blueprint $table): void {
                $table->dropUnique('customer_touchpoints_campaign_run_unique');
                $table->dropIndex('customer_touchpoints_campaign_run_idx');

                foreach (['campaign_run_key', 'campaign_id'] as $column) {
                    if (Schema::hasColumn('customer_touchpoints', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('campaign_dispatch_runs');
    }
};
