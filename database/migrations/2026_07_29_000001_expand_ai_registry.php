<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('name', 160);
            $table->string('status', 24)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_provider_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->unsignedInteger('version');
            $table->char('checksum', 64);
            $table->json('configuration');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('provider_id', 'ai_provider_versions_provider_fk')
                ->references('id')->on('ai_providers')->cascadeOnDelete();
            $table->unique(['provider_id', 'version'], 'ai_provider_versions_number_unique');
            $table->unique(['provider_id', 'checksum'], 'ai_provider_versions_checksum_unique');
        });

        Schema::create('ai_models', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->string('key', 128);
            $table->string('name', 160);
            $table->string('status', 24)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('provider_id', 'ai_models_provider_fk')
                ->references('id')->on('ai_providers')->cascadeOnDelete();
            $table->unique(['provider_id', 'key'], 'ai_models_provider_key_unique');
        });

        Schema::create('ai_model_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('model_id');
            $table->uuid('provider_version_id')->nullable();
            $table->unsignedInteger('version');
            $table->char('checksum', 64);
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedBigInteger('input_cost_minor')->nullable();
            $table->unsignedBigInteger('output_cost_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('configuration');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('model_id', 'ai_model_versions_model_fk')
                ->references('id')->on('ai_models')->cascadeOnDelete();
            $table->foreign('provider_version_id', 'ai_model_versions_provider_version_fk')
                ->references('id')->on('ai_provider_versions')->nullOnDelete();
            $table->unique(['model_id', 'version'], 'ai_model_versions_number_unique');
            $table->unique(['model_id', 'checksum'], 'ai_model_versions_checksum_unique');
        });

        Schema::create('ai_agent_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('name', 160);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 24)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key'], 'ai_agent_definitions_tenant_key_unique');
        });

        Schema::create('ai_agent_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('agent_definition_id');
            $table->uuid('model_version_id')->nullable();
            $table->unsignedInteger('version');
            $table->char('checksum', 64);
            $table->json('configuration');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('agent_definition_id', 'ai_agent_versions_definition_fk')
                ->references('id')->on('ai_agent_definitions')->cascadeOnDelete();
            $table->foreign('model_version_id', 'ai_agent_versions_model_version_fk')
                ->references('id')->on('ai_model_versions')->nullOnDelete();
            $table->unique(['agent_definition_id', 'version'], 'ai_agent_versions_number_unique');
            $table->unique(['agent_definition_id', 'checksum'], 'ai_agent_versions_checksum_unique');
            $table->index(['tenant_id', 'agent_definition_id'], 'ai_agent_versions_tenant_definition_idx');
        });

        Schema::create('ai_prompt_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('name', 160);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 24)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key'], 'ai_prompt_definitions_tenant_key_unique');
        });

        Schema::create('ai_prompt_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('prompt_definition_id');
            $table->unsignedInteger('version');
            $table->char('checksum', 64);
            $table->longText('template_ciphertext');
            $table->char('template_hash', 64);
            $table->string('encryption_key_version', 32);
            $table->json('input_schema')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('prompt_definition_id', 'ai_prompt_versions_definition_fk')
                ->references('id')->on('ai_prompt_definitions')->cascadeOnDelete();
            $table->unique(['prompt_definition_id', 'version'], 'ai_prompt_versions_number_unique');
            $table->unique(['prompt_definition_id', 'checksum'], 'ai_prompt_versions_checksum_unique');
            $table->index(['tenant_id', 'prompt_definition_id'], 'ai_prompt_versions_tenant_definition_idx');
        });

        Schema::create('ai_release_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('name', 160);
            $table->char('checksum', 64);
            $table->json('rules');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'key'], 'ai_release_policies_tenant_key_unique');
            $table->unique(['tenant_id', 'checksum'], 'ai_release_policies_tenant_checksum_unique');
        });

        Schema::create('ai_agent_activations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('scope_type', 32);
            $table->string('scope_key', 190);
            $table->uuid('agent_version_id');
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->char('cohort_salt', 64);
            $table->uuid('release_decision_id')->nullable();
            $table->uuid('previous_activation_id')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->foreign('agent_version_id', 'ai_agent_activations_agent_version_fk')
                ->references('id')->on('ai_agent_versions')->restrictOnDelete();
            $table->foreign('previous_activation_id', 'ai_agent_activations_previous_fk')
                ->references('id')->on('ai_agent_activations')->nullOnDelete();
            $table->index(['tenant_id', 'scope_type', 'scope_key'], 'ai_agent_activations_scope_idx');
            $table->index(['tenant_id', 'effective_from'], 'ai_agent_activations_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_activations');
        Schema::dropIfExists('ai_release_policies');
        Schema::dropIfExists('ai_prompt_versions');
        Schema::dropIfExists('ai_prompt_definitions');
        Schema::dropIfExists('ai_agent_versions');
        Schema::dropIfExists('ai_agent_definitions');
        Schema::dropIfExists('ai_model_versions');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_provider_versions');
        Schema::dropIfExists('ai_providers');
    }
};
