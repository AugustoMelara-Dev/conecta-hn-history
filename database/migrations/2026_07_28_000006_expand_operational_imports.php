<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_import_batches', function (Blueprint $table): void {
            $table->string('kind', 32)->nullable();
            $table->string('source_origin', 32)->default('upload');
            $table->string('idempotency_key', 160)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->string('source_disk', 32)->nullable();
            $table->string('source_path', 2048)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();

            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'catalog_import_batches_tenant_idempotency_unique',
            );
        });

        Schema::table('catalog_import_items', function (Blueprint $table): void {
            $table->unsignedInteger('row_number')->nullable();
            $table->string('aggregate_type', 32)->nullable();
            $table->string('aggregate_id', 64)->nullable();
            $table->json('source_payload')->nullable();
            $table->json('validation_errors')->nullable();

            $table->unique(
                ['batch_id', 'row_number'],
                'catalog_import_items_batch_row_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('catalog_import_items', function (Blueprint $table): void {
            $table->dropUnique('catalog_import_items_batch_row_unique');
            $table->dropColumn([
                'row_number',
                'aggregate_type',
                'aggregate_id',
                'source_payload',
                'validation_errors',
            ]);
        });
        Schema::table('catalog_import_batches', function (Blueprint $table): void {
            $table->dropUnique('catalog_import_batches_tenant_idempotency_unique');
            $table->dropColumn([
                'kind',
                'source_origin',
                'idempotency_key',
                'payload_hash',
                'correlation_id',
                'source_disk',
                'source_path',
                'original_name',
                'mime_type',
                'size_bytes',
                'sha256',
            ]);
        });
    }
};
