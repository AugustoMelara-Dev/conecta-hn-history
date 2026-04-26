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
        if (! Schema::hasTable('tenant_knowledge_embeddings')) {
            Schema::create('tenant_knowledge_embeddings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('source_type', 64);
                $table->string('source_id', 128);
                $table->string('title', 180);
                $table->string('embedding_model', 80);
                $table->string('content_hash', 64);
                $table->longText('embedding_input');
                $table->longText('embedding_json')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('indexed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'source_type', 'source_id', 'embedding_model'], 'tenant_knowledge_source_unique');
                $table->index(['tenant_id', 'source_type', 'indexed_at'], 'tenant_knowledge_source_idx');
                $table->index(['tenant_id', 'content_hash'], 'tenant_knowledge_hash_idx');
            });
        }

        if (! Schema::hasTable('business_ai_rag_audits')) {
            Schema::create('business_ai_rag_audits', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('query_hash', 64)->index();
                $table->string('query_excerpt', 220)->nullable();
                $table->text('answer_excerpt')->nullable();
                $table->unsignedSmallInteger('source_count')->default(0);
                $table->unsignedInteger('latency_ms')->default(0);
                $table->string('degraded_reason', 80)->nullable()->index();
                $table->unsignedSmallInteger('status_code')->default(200)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at'], 'business_ai_rag_tenant_created_idx');
            });
        }

        if (DB::getDriverName() !== 'pgsql' || ! (bool) env('PGVECTOR_ENABLED', false)) {
            return;
        }

        $dimension = max(8, (int) env('PGVECTOR_DIMENSION', 1536));

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement("ALTER TABLE tenant_knowledge_embeddings ADD COLUMN IF NOT EXISTS embedding vector({$dimension})");
        } catch (Throwable) {
            // JSON embeddings are the portable fallback for local/test environments.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_ai_rag_audits');
        Schema::dropIfExists('tenant_knowledge_embeddings');
    }
};
