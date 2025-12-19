<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * HORARIOS DE APERTURA - Conecta HN
     * Permite intervalos múltiples por día (ej. 8:00-12:00 y 14:00-18:00)
     * Reemplaza el campo JSON simple para permitir consultas SQL de "Abierto Ahora"
     */
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
            $table->unsignedTinyInteger('day_of_week'); 
            
            $table->time('opening_time');
            $table->time('closing_time');
            
            $table->timestamps();

            // Índices para consultas rápidas de "Abierto Ahora"
            // WHERE tenant_id = ? AND day_of_week = ? AND opening_time <= ? AND closing_time >= ?
            $table->index(['tenant_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};
