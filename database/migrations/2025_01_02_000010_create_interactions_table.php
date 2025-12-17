<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            
            // Tipo de interacción
            $table->string('type', 50)->default('visit');
            
            // Datos del visitante
            $table->string('ip_address', 45)->nullable(); // IPv4 o IPv6
            $table->string('user_agent', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('url', 500)->nullable();
            
            // Metadata adicional (JSON)
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Índices para queries de analytics
            $table->index('type');
            $table->index('created_at');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
