<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->char('operation_key_hash', 64);
            $table->char('payload_hash', 64);
            $table->char('staged_path_hash', 64)->nullable();
            $table->char('source_digest', 64)->nullable();
            $table->string('action', 16);
            $table->string('published_path')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'operation_key_hash'], 'product_media_operation_tenant_key_unique');
            $table->foreign(['tenant_id', 'product_id'], 'product_media_operation_product_fk')
                ->references(['tenant_id', 'id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('product_media_cleanup_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('disk', 16);
            $table->string('path');
            $table->char('path_hash', 64);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['disk', 'path_hash'], 'product_media_cleanup_disk_path_unique');
            $table->index(['available_at', 'id'], 'product_media_cleanup_available_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media_cleanup_tasks');
        Schema::dropIfExists('product_media_operations');
    }
};
