<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('directory_product_embeddings')) {
            return;
        }

        Schema::create('directory_product_embeddings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->string('embedding_model', 64);
            $table->string('embedding_hash', 64);
            $table->text('embedding_input');
            $table->longText('embedding_json')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'embedding_model'], 'dir_product_embedding_unique');
            $table->index(['tenant_id', 'product_id'], 'dir_product_embedding_tenant_product_idx');
            $table->index(['tenant_id', 'embedding_hash'], 'dir_product_embedding_hash_idx');
        });

        DB::table('directory_product_embeddings')
            ->whereNull('id')
            ->update(['id' => (string) Str::uuid()]);

        if (DB::getDriverName() !== 'pgsql' || ! (bool) env('PGVECTOR_ENABLED', false)) {
            return;
        }

        $dimension = max(8, (int) env('PGVECTOR_DIMENSION', 1536));

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement("ALTER TABLE directory_product_embeddings ADD COLUMN IF NOT EXISTS embedding vector({$dimension})");
        } catch (\Throwable) {
            // PGVECTOR can be enabled progressively; JSON storage remains the safe fallback.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_product_embeddings');
    }
};
