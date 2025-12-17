<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * SISTEMA DE ÓRDENES - Conecta HN
     * Tabla principal para trazabilidad de pedidos y el "Botón Mágico de WhatsApp"
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // ═══ IDENTIFICACIÓN ═══
            $table->string('order_number', 20)->unique();
            
            // ═══ ESTADO DEL PEDIDO ═══
            $table->enum('status', [
                'pending',      // Recién creado, esperando confirmación
                'confirmed',    // Negocio confirmó el pedido
                'preparing',    // En preparación (cocina)
                'ready',        // Listo para entrega/recolección
                'delivered',    // Entregado al cliente
                'cancelled'     // Cancelado
            ])->default('pending');
            
            // ═══ INFORMACIÓN DEL CLIENTE ═══
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('customer_email')->nullable();
            $table->text('delivery_address')->nullable();
            
            // ═══ MONTOS ═══
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            
            // ═══ TRAZABILIDAD WHATSAPP (THE KILLER FEATURE) ═══
            $table->enum('source', ['whatsapp', 'web', 'direct'])->default('whatsapp');
            $table->string('whatsapp_message_id', 100)->nullable();
            $table->string('whatsapp_conversation_id', 100)->nullable();
            
            // ═══ INFORMACIÓN DE PAGO ═══
            $table->enum('payment_method', [
                'cash',         // Efectivo al entregar
                'transfer',     // Transferencia bancaria
                'card',         // Tarjeta (BAC Credomatic futuro)
                'pending'       // Sin definir aún
            ])->default('pending');
            
            $table->enum('payment_status', [
                'pending',      // Pendiente de pago
                'paid',         // Pagado
                'failed',       // Pago fallido
                'refunded'      // Reembolsado
            ])->default('pending');
            
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // ═══ NOTAS ═══
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            
            // ═══ TIMESTAMPS DE FLUJO ═══
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            // ═══ ÍNDICES PARA ESCALABILIDAD ═══
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('order_number');
            $table->index(['tenant_id', 'customer_phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
