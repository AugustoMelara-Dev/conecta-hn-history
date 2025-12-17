<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * GRUPOS DE OPCIONES DE PRODUCTO - Conecta HN
     * Permite crear grupos como "Elige tu Tamaño" o "Salsas Extra" para restaurantes
     */
    public function up(): void
    {
        Schema::create('product_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // ═══ INFORMACIÓN DEL GRUPO ═══
            $table->string('name');  // "Tamaño", "Salsas Extra", "Acompañantes"
            
            // ═══ TIPO DE SELECCIÓN ═══
            $table->enum('type', [
                'single',    // Solo puede elegir UNA opción (radio buttons)
                'multiple'   // Puede elegir VARIAS opciones (checkboxes)
            ])->default('single');
            
            // ═══ REGLAS DE VALIDACIÓN ═══
            $table->boolean('is_required')->default(false);
            $table->integer('min_selections')->default(0);        // Mínimo de opciones a seleccionar
            $table->integer('max_selections')->nullable();        // Máximo de opciones (null = ilimitado)
            
            // ═══ ORDENAMIENTO ═══
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            // ═══ ÍNDICES ═══
            $table->index(['product_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_option_groups');
    }
};
