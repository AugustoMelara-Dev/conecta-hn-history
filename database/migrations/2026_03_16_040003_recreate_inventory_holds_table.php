<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_holds')) {
            Schema::create('inventory_holds', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('session_id');
                $table->foreignUuid('location_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->unique(['tenant_id', 'session_id', 'location_id', 'product_id'], 'inv_holds_unique');
                $table->index('expires_at');
            });

            return;
        }

        Schema::table('inventory_holds', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_holds', 'location_id')) {
                $table->foreignUuid('location_id')->nullable()->after('session_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_holds');
    }
};
