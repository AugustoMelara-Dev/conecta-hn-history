<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EMBEDDING_DIMENSIONS = 1536;

    public function up(): void
    {
        $postgres = DB::getDriverName() === 'pgsql';

        if ($postgres) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('ai_knowledge_chunks', function (Blueprint $table) use ($postgres): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('resource_type', 32);
            $table->string('resource_id', 64);
            $table->string('visibility', 16);
            $table->text('content');
            $table->char('content_hash', 64);
            if (! $postgres) {
                $table->json('embedding');
            }
            $table->string('embedding_model', 100);
            $table->json('metadata')->nullable();
            $table->timestampTz('indexed_at');
            $table->timestampsTz();

            $table->unique(
                ['tenant_id', 'resource_type', 'resource_id'],
                'ai_knowledge_chunks_resource_unique',
            );
            $table->index(['visibility', 'resource_type'], 'ai_knowledge_chunks_public_lookup');
            $table->index(['tenant_id', 'visibility'], 'ai_knowledge_chunks_tenant_lookup');
        });

        if (! $postgres) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE ai_knowledge_chunks ADD COLUMN embedding vector(%d) NOT NULL',
            self::EMBEDDING_DIMENSIONS,
        ));
        DB::statement(
            'CREATE INDEX ai_knowledge_chunks_embedding_hnsw '
            .'ON ai_knowledge_chunks USING hnsw (embedding vector_cosine_ops)',
        );

        DB::statement('ALTER TABLE ai_knowledge_chunks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ai_knowledge_chunks FORCE ROW LEVEL SECURITY');
        DB::statement(
            "CREATE POLICY ai_knowledge_chunks_select ON ai_knowledge_chunks FOR SELECT "
            ."USING (visibility = 'public' OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)",
        );
        DB::statement(
            'CREATE POLICY ai_knowledge_chunks_insert ON ai_knowledge_chunks FOR INSERT '
            ."WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)",
        );
        DB::statement(
            'CREATE POLICY ai_knowledge_chunks_update ON ai_knowledge_chunks FOR UPDATE '
            ."USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid) "
            ."WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)",
        );
        DB::statement(
            'CREATE POLICY ai_knowledge_chunks_delete ON ai_knowledge_chunks FOR DELETE '
            ."USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};
