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

        // crm_deals is created by the canonical UUID CRM migration after companies and contacts.
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
