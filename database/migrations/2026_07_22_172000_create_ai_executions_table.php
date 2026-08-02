<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_executions', function (Blueprint $table): void {
            $table->uuid('correlation_id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('billing_tenant_id')->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->string('audience', 16);
            $table->string('capability', 64);
            $table->string('operation', 100);
            $table->string('provider', 32);
            $table->char('request_hash', 64);
            $table->char('claim_token', 64);
            $table->unsignedInteger('claim_version')->default(1);
            $table->string('status', 24);
            $table->string('reservation_id', 32)->nullable();
            $table->longText('result_ciphertext')->nullable();
            $table->char('result_hash', 64)->nullable();
            $table->string('model', 100)->nullable();
            $table->json('usage')->nullable();
            $table->unsignedInteger('latency_milliseconds')->nullable();
            $table->decimal('provider_cost', 12, 6)->nullable();
            $table->decimal('charged_cost', 12, 6)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('encryption_key_version', 32);
            $table->timestamp('retention_expires_at');
            $table->timestamp('processing_expires_at');
            $table->timestamp('provider_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('payload_pruned_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'ai_executions_tenant_fk')
                ->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('billing_tenant_id', 'ai_executions_billing_tenant_fk')
                ->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('reservation_id', 'ai_executions_reservation_fk')
                ->references('id')->on('ai_credit_reservations')->nullOnDelete();
            $table->index(['status', 'processing_expires_at'], 'ai_executions_reconcile_idx');
            $table->index(['status', 'retention_expires_at'], 'ai_executions_retention_idx');
            $table->index(['billing_tenant_id', 'created_at'], 'ai_executions_billing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_executions');
    }
};
