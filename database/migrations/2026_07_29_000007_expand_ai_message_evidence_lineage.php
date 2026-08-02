<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_message_evidence', function (Blueprint $table): void {
            $table->string('evidence_id', 128)->nullable();
            $table->string('source_type', 32)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->char('source_version', 64)->nullable();

            // A public conversation remains platform-owned while this column
            // freezes the merchant source. Deliberately no cascading FK: a
            // source-owner lifecycle must not erase an immutable snapshot.
            $table->uuid('source_tenant_id')->nullable();
            $table->string('visibility', 16)->nullable();
            $table->string('citation_label', 160)->nullable();
            $table->timestampTz('fresh_at')->nullable();
            $table->unsignedSmallInteger('retrieval_rank')->nullable();
            $table->decimal('retrieval_score', 9, 8)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('availability', 32)->nullable();
            $table->longText('excerpt_ciphertext')->nullable();
            $table->char('excerpt_hash', 64)->nullable();
            $table->string('excerpt_encryption_key_version', 32)->nullable();

            $table->unique(
                ['tenant_id', 'message_id', 'evidence_id'],
                'ai_message_evidence_message_evidence_unique',
            );
            $table->index(
                ['source_tenant_id', 'source_type', 'source_id', 'source_version'],
                'ai_message_evidence_source_version_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_message_evidence', function (Blueprint $table): void {
            $table->dropUnique('ai_message_evidence_message_evidence_unique');
            $table->dropIndex('ai_message_evidence_source_version_idx');
            $table->dropColumn([
                'evidence_id',
                'source_type',
                'source_id',
                'source_version',
                'source_tenant_id',
                'visibility',
                'citation_label',
                'fresh_at',
                'retrieval_rank',
                'retrieval_score',
                'currency',
                'price_minor',
                'availability',
                'excerpt_ciphertext',
                'excerpt_hash',
                'excerpt_encryption_key_version',
            ]);
        });
    }
};
