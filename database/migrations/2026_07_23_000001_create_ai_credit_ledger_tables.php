<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credit_ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('entry_type', 40);
            $table->decimal('balance_delta', 12, 6)->default(0);
            $table->decimal('reserved_delta', 12, 6)->default(0);
            $table->string('reservation_id', 32)->nullable();
            $table->string('billing_period', 7)->nullable();
            $table->string('idempotency_key', 160);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('tenant_id', 'ai_credit_ledger_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->foreign('reservation_id', 'ai_credit_ledger_reservation_fk')
                ->references('id')
                ->on('ai_credit_reservations')
                ->nullOnDelete();
            $table->unique(['tenant_id', 'idempotency_key'], 'ai_credit_ledger_tenant_idempotency_uq');
            $table->index(['tenant_id', 'occurred_at'], 'ai_credit_ledger_tenant_time_idx');
            $table->index(['tenant_id', 'billing_period', 'entry_type'], 'ai_credit_ledger_period_idx');
        });

        Schema::create('ai_credit_usage_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('billing_period', 7);
            $table->unsignedSmallInteger('threshold');
            $table->unsignedSmallInteger('usage_percentage');
            $table->timestamp('created_at');

            $table->foreign('tenant_id', 'ai_credit_alerts_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'billing_period', 'threshold'],
                'ai_credit_alerts_tenant_period_threshold_uq',
            );
        });
        Schema::create('ai_credit_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('billing_period', 7);
            $table->string('plan', 30);
            $table->unsignedInteger('transition_sequence');
            $table->decimal('amount', 12, 6);
            $table->timestamp('granted_at');
            $table->timestamps();

            $table->foreign('tenant_id', 'ai_credit_grants_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->unique(['tenant_id', 'billing_period', 'transition_sequence'], 'ai_credit_grants_tenant_period_seq_uq');
            $table->index(['tenant_id', 'billing_period', 'granted_at'], 'ai_credit_grants_tenant_period_idx');
            $table->index(['billing_period', 'granted_at'], 'ai_credit_grants_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_grants');
        Schema::dropIfExists('ai_credit_usage_alerts');
        Schema::dropIfExists('ai_credit_ledger_entries');
    }
};
