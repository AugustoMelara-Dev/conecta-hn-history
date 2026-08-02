<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_billing_market_providers', function (Blueprint $table): void {
            $table->string('market_key', 16)->primary();
            $table->string('provider_key', 64);
            $table->string('checkout_mode', 32)->default('external');
            $table->string('signing_secret', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('global_billing_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('market_key', 16);
            $table->string('price_book_version', 64);
            $table->string('plan_key', 32);
            $table->string('currency_code', 3);
            $table->string('status', 32);
            $table->boolean('sandbox_access')->default(false);
            $table->boolean('live_access')->default(false);
            $table->decimal('included_credits', 12, 6)->default(0);
            $table->timestamp('grace_ends_at')->nullable();
            $table->string('provider_key', 64)->nullable();
            $table->string('provider_event_id', 128)->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at'], 'global_billing_entitlements_tenant_created_idx');
        });

        Schema::create('global_billing_credit_accounts', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->decimal('available_credits', 12, 6)->default(0);
            $table->decimal('reserved_credits', 12, 6)->default(0);
            $table->decimal('allowance_credits', 12, 6)->default(0);
            $table->decimal('spent_credits', 12, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('global_billing_credit_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('operation', 120);
            $table->decimal('authorized_credits', 12, 6);
            $table->decimal('actual_credits', 12, 6)->nullable();
            $table->string('status', 32);
            $table->string('idempotency_key', 160)->unique();
            $table->string('provider_run_id', 160)->nullable();
            $table->string('failure_reason', 120)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status'], 'global_billing_credit_reservations_tenant_status_idx');
        });

        Schema::create('global_billing_credit_ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('reservation_id')->nullable();
            $table->string('entry_type', 64);
            $table->decimal('credit_delta', 12, 6);
            $table->decimal('reserved_delta', 12, 6);
            $table->decimal('balance_after', 12, 6);
            $table->decimal('reserved_after', 12, 6);
            $table->string('idempotency_key', 160)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['tenant_id', 'occurred_at'], 'global_billing_credit_ledger_entries_tenant_occurred_idx');
        });

        Schema::create('global_billing_credit_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('threshold');
            $table->timestamp('triggered_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'threshold'], 'global_billing_credit_alerts_tenant_threshold_unique');
        });

        Schema::create('global_billing_credit_reconciliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('reservation_id')->unique();
            $table->string('provider_run_id', 160);
            $table->decimal('authorized_credits', 12, 6);
            $table->decimal('actual_credits', 12, 6);
            $table->decimal('variance_credits', 12, 6)->default(0);
            $table->string('status', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('global_billing_lifecycle_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('market_key', 16);
            $table->string('provider_key', 64);
            $table->string('provider_event_id', 128);
            $table->string('event_type', 128);
            $table->string('signature', 255);
            $table->string('payload_sha256', 64);
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('authenticity_status', 32);
            $table->boolean('replayed')->default(false);
            $table->timestamp('processed_at');
            $table->longText('payload');
            $table->timestamps();
            $table->unique(['provider_key', 'provider_event_id'], 'global_billing_lifecycle_events_provider_event_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Program 09 global billing migration is forward-only.');
    }
};
