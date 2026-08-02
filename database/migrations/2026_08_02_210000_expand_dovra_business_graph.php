<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dovra_business_contexts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('context_key', 96);
            $table->json('value');
            $table->string('source_type', 64);
            $table->string('source_id', 128);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'context_key', 'source_type', 'source_id'],
                'dovra_business_contexts_source_unique',
            );
            $table->index(['tenant_id', 'context_key'], 'dovra_business_contexts_tenant_key_idx');
            $table->index(['tenant_id', 'revoked_at', 'valid_until'], 'dovra_business_contexts_current_idx');
        });

        Schema::create('dovra_business_facts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->string('subject_id', 128);
            $table->string('fact_key', 96);
            $table->json('value');
            $table->string('source_type', 64);
            $table->string('source_id', 128);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'subject_type', 'subject_id', 'fact_key', 'source_type', 'source_id'],
                'dovra_business_facts_source_unique',
            );
            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'dovra_business_facts_subject_idx');
            $table->index(['tenant_id', 'fact_key'], 'dovra_business_facts_key_idx');
            $table->index(['tenant_id', 'revoked_at', 'valid_until'], 'dovra_business_facts_current_idx');
        });

        Schema::create('dovra_business_edges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('from_type', 64);
            $table->string('from_id', 128);
            $table->string('relation', 64);
            $table->string('to_type', 64);
            $table->string('to_id', 128);
            $table->string('source_type', 64);
            $table->string('source_id', 128);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'from_type', 'from_id', 'relation', 'to_type', 'to_id', 'source_type', 'source_id'],
                'dovra_business_edges_source_unique',
            );
            $table->index(['tenant_id', 'from_type', 'from_id'], 'dovra_business_edges_from_idx');
            $table->index(['tenant_id', 'relation'], 'dovra_business_edges_relation_idx');
            $table->index(['tenant_id', 'revoked_at', 'valid_until'], 'dovra_business_edges_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dovra_business_edges');
        Schema::dropIfExists('dovra_business_facts');
        Schema::dropIfExists('dovra_business_contexts');
    }
};
