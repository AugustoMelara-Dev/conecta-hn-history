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
        Schema::create('ai_credit_reservations', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->uuid('tenant_id');
            $table->string('operation', 100);
            $table->decimal('estimated_cost', 12, 6);
            $table->decimal('actual_cost', 12, 6)->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('usage_metadata')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'ai_credit_reservations_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'created_at'], 'ai_credit_reservations_reconcile_idx');
        });

        // Legacy reservations were not identifiable or safely reconcilable.
        // Deployments must drain AI workers before this one-time reset.
        DB::table('tenants')->update(['ai_reserved_balance' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_reservations');
    }
};
