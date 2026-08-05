<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->uuid('id')->unique();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('delta');
            $table->integer('previous_quantity');
            $table->integer('resulting_quantity');
            $table->string('reason', 64);
            $table->string('actor_type', 100)->nullable();
            $table->string('actor_id', 100)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('idempotency_key', 191);
            $table->char('payload_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'inventory_movements_tenant_idempotency_unique');
            $table->index(['tenant_id', 'product_id', 'created_at'], 'inventory_movements_tenant_product_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
