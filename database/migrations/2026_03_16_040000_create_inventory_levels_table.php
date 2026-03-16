<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_levels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('on_hand', 12, 3)->default(0);
            $table->decimal('reserved', 12, 3)->default(0);
            $table->decimal('low_stock_threshold', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'product_id']);
            $table->index(['tenant_id', 'location_id']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_levels');
    }
};
