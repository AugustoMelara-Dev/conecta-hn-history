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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            // Identidad del Negocio
            $table->string('name');
            $table->string('slug')->unique()->index(); // Para conectahn.com/slug
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->default('#000000'); // Personalización básica

            // Estado de Suscripción (Lógica Freemium)
            $table->boolean('is_pro')->default(false);
            $table->timestamp('plan_expires_at')->nullable();

            // Datos de Contacto (Para el botón de WhatsApp y Footer)
            $table->string('whatsapp_number')->nullable();
            $table->text('address')->nullable();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Dueño del negocio
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
