<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->timestamp('dispatch_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('failure_reason', 64)->nullable();
            $table->index(['status', 'scheduled_at'], 'campaigns_due_schedule_idx');
            $table->index(['tenant_id', 'status'], 'campaigns_tenant_status_idx');
        });

        DB::table('campaigns')
            ->whereNull('subject')
            ->update(['subject' => DB::raw('title')]);

        Schema::create('campaign_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignId('campaign_id');
            $table->uuid('user_id');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('failure_reason', 64)->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'campaign_id', 'user_id'], 'campaign_deliveries_recipient_unique');
            $table->index(['tenant_id', 'campaign_id', 'status'], 'campaign_deliveries_status_idx');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_deliveries');

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropIndex('campaigns_due_schedule_idx');
            $table->dropIndex('campaigns_tenant_status_idx');
            $table->dropColumn([
                'delivered_count',
                'failed_count',
                'blocked_count',
                'dispatch_started_at',
                'completed_at',
                'failure_reason',
            ]);
        });
    }
};
