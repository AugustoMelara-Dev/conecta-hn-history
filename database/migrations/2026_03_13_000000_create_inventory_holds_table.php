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
                $table->id();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('session_id')->index();
                $table->integer('quantity');
                $table->timestamp('expires_at')->index();
                $table->timestamps();

                $table->unique(['tenant_id', 'session_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('inventory_holds');
        Schema::enableForeignKeyConstraints();
    }
};
