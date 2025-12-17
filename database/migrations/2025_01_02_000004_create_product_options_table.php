<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * OPCIONES DE PRODUCTO - Conecta HN
     * Las opciones individuales dentro de cada grupo (ej: "Grande", "Salsa BBQ")
     */
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')
                ->constrained('product_option_groups')
                ->cascadeOnDelete();

            // ═══ INFORMACIÓN DE LA OPCIÓN ═══
            $table->string('name');  // "Grande", "Salsa BBQ", "Aguacate Extra"

            // ═══ MODIFICADOR DE PRECIO ═══
            // Puede ser positivo (+L.15) o negativo (-L.5 para descuento)
            $table->decimal('price_modifier', 10, 2)->default(0);

            // ═══ CONFIGURACIÓN ═══
            $table->boolean('is_default')->default(false);     // Preseleccionado
            $table->boolean('is_available')->default(true);    // Disponible para ordenar

            // ═══ ORDENAMIENTO ═══
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // ═══ ÍNDICES ═══
            $table->index(['option_group_id', 'sort_order']);
            $table->index(['option_group_id', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
