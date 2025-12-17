<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ITEMS DE ORDEN - Conecta HN
     * Almacena los productos de cada pedido con snapshot de precio y opciones seleccionadas
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // ═══ SNAPSHOT DEL PRODUCTO AL MOMENTO DE LA ORDEN ═══
            // Guardamos nombre y precio porque el producto puede cambiar o eliminarse
            $table->string('product_name');
            $table->decimal('product_price', 10, 2);

            // ═══ CANTIDADES Y PRECIOS ═══
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);   // Precio con modificadores aplicados
            $table->decimal('total_price', 10, 2);  // unit_price * quantity

            // ═══ OPCIONES SELECCIONADAS (SNAPSHOT) ═══
            // JSON con las variantes/addons que eligió el cliente
            // Ejemplo: [{"group": "Tamaño", "option": "Grande", "modifier": 15.00}]
            $table->json('selected_options')->nullable();

            // ═══ NOTAS DEL ITEM ═══
            $table->text('notes')->nullable();

            $table->timestamps();

            // ═══ ÍNDICES ═══
            $table->index('order_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
