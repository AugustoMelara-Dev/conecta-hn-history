<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_deals')) {
            Schema::create('crm_deals', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('crm_pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
                $table->foreignId('crm_stage_id')->constrained('crm_stages')->restrictOnDelete();
                $table->foreignId('customer_profile_id')->nullable()->constrained('customer_profiles')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->uuid('location_id')->nullable();
                $table->string('title');
                $table->decimal('value', 12, 2)->default(0);
                $table->string('currency', 3)->default('HNL');
                $table->string('status', 16)->default('open');
                $table->string('source')->nullable();
                $table->date('expected_close_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('lost_reason')->nullable();
                $table->text('notes')->nullable();
                $table->string('write_idempotency_key', 160)->nullable();
                $table->char('write_payload_hash', 64)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'crm_stage_id', 'status'], 'crm_deals_tenant_stage_status_idx');
                $table->index(['tenant_id', 'owner_user_id', 'status'], 'crm_deals_tenant_owner_status_idx');
                $table->index(['tenant_id', 'customer_profile_id'], 'crm_deals_tenant_customer_idx');
                $table->index(['tenant_id', 'lead_id'], 'crm_deals_tenant_lead_idx');
                $table->index(['tenant_id', 'location_id'], 'crm_deals_tenant_location_idx');
                $table->index(['tenant_id', 'expected_close_at'], 'crm_deals_tenant_expected_close_idx');
                $table->unique(['tenant_id', 'write_idempotency_key'], 'crm_deals_tenant_write_key_unique');
            });
        }
    }

    public function down(): void
    {
        // This compatibility migration never owns an existing canonical table.
    }
};
