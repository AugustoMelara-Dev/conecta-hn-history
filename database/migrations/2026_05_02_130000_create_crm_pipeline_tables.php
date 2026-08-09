<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name');
                $table->string('key');
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'key'], 'crm_pipelines_tenant_key_unique');
                $table->index(['tenant_id', 'is_active', 'sort_order'], 'crm_pipelines_tenant_active_sort_idx');
            });
        }

        if (! Schema::hasTable('crm_stages')) {
            Schema::create('crm_stages', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('crm_pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
                $table->string('name');
                $table->string('key');
                $table->string('type', 16)->default('open');
                $table->unsignedTinyInteger('probability')->default(0);
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['crm_pipeline_id', 'key'], 'crm_stages_pipeline_key_unique');
                $table->index(['tenant_id', 'crm_pipeline_id', 'sort_order'], 'crm_stages_tenant_pipeline_sort_idx');
                $table->index(['tenant_id', 'type'], 'crm_stages_tenant_type_idx');
            });
        }

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
                $table->timestamps();

                $table->index(['tenant_id', 'crm_stage_id', 'status'], 'crm_deals_tenant_stage_status_idx');
                $table->index(['tenant_id', 'owner_user_id', 'status'], 'crm_deals_tenant_owner_status_idx');
                $table->index(['tenant_id', 'customer_profile_id'], 'crm_deals_tenant_customer_idx');
                $table->index(['tenant_id', 'lead_id'], 'crm_deals_tenant_lead_idx');
                $table->index(['tenant_id', 'location_id'], 'crm_deals_tenant_location_idx');
                $table->index(['tenant_id', 'expected_close_at'], 'crm_deals_tenant_expected_close_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
