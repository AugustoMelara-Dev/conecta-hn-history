<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order Management System Migration
 * 
 * Creates a persistent order ledger with:
 * - orders: Header with customer info, status workflow, and totals
 * - order_items: Line items with price snapshots and customizations
 * 
 * MULTI-TENANT: All queries MUST filter by tenant_id
 * 
 * WORKFLOW STATES:
 * pending → processing → ready → delivered
 *                              → cancelled
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // ORDERS TABLE: Transaction Header
        // ═══════════════════════════════════════════════════════════════════════
        // Note: May already exist from earlier migration - use conditional creation
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                
                // ═══════════════════════════════════════════════════════════════════
                // ORDER IDENTIFICATION
                // ═══════════════════════════════════════════════════════════════════
                // Human-readable order number (unique per tenant)
                // Format: #1001, #1002, etc. - reset per tenant
                $table->string('order_number', 20);
                
                // ═══════════════════════════════════════════════════════════════════
                // CUSTOMER INFORMATION
                // ═══════════════════════════════════════════════════════════════════
                $table->string('customer_name');
                $table->string('customer_phone', 20);
                $table->string('customer_email')->nullable();
                $table->text('customer_address')->nullable();
                $table->text('customer_notes')->nullable();  // Special instructions
                
                // ═══════════════════════════════════════════════════════════════════
                // FINANCIAL DATA
                // ═══════════════════════════════════════════════════════════════════
                $table->decimal('subtotal', 10, 2);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->decimal('delivery_fee', 10, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('total', 10, 2);
                
                // ═══════════════════════════════════════════════════════════════════
                // LOGISTICS
                // ═══════════════════════════════════════════════════════════════════
                $table->enum('payment_method', ['cash', 'card', 'transfer', 'pending'])->default('pending');
                $table->enum('delivery_type', ['pickup', 'delivery'])->default('pickup');
                $table->timestamp('estimated_ready_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                
                // ═══════════════════════════════════════════════════════════════════
                // WORKFLOW STATUS (Kanban columns)
                // ═══════════════════════════════════════════════════════════════════
                $table->enum('status', [
                    'pending',      // Just received
                    'confirmed',    // Accepted by merchant
                    'processing',   // Being prepared
                    'ready',        // Ready for pickup/delivery
                    'delivered',    // Completed
                    'cancelled',    // Cancelled
                ])->default('pending');
                
                // Reason for cancellation (if applicable)
                $table->string('cancel_reason')->nullable();
                
                // ═══════════════════════════════════════════════════════════════════
                // WHATSAPP TRACKING
                // ═══════════════════════════════════════════════════════════════════
                $table->boolean('whatsapp_sent')->default(false);
                $table->timestamp('whatsapp_sent_at')->nullable();
                
                $table->timestamps();
                $table->softDeletes();
                
                // ═══════════════════════════════════════════════════════════════════
                // INDEXES FOR PERFORMANCE
                // ═══════════════════════════════════════════════════════════════════
                $table->unique(['tenant_id', 'order_number']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'customer_phone']);
            });
        }

        // ═══════════════════════════════════════════════════════════════════════
        // ORDER_ITEMS TABLE: Line Item Details with Snapshots
        // ═══════════════════════════════════════════════════════════════════════
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                
                // Product reference (nullable in case product is deleted later)
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                
                // ═══════════════════════════════════════════════════════════════════
                // PRICE SNAPSHOT: Frozen at time of purchase
                // These values NEVER change even if product is updated later
                // ═══════════════════════════════════════════════════════════════════
                $table->string('product_name');
                $table->string('product_sku')->nullable();
                $table->decimal('unit_price', 10, 2);  // Price at time of purchase
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('line_total', 10, 2);  // unit_price * quantity + modifiers
                
                // ═══════════════════════════════════════════════════════════════════
                // CUSTOMIZATIONS: Modifier selections
                // ═══════════════════════════════════════════════════════════════════
                // JSON structure:
                // [
                //   { "group": "Tamaño", "option": "Grande", "price": 50.00 },
                //   { "group": "Extras", "option": "Queso extra", "price": 25.00 }
                // ]
                $table->json('options')->nullable();
                $table->decimal('options_total', 10, 2)->default(0);  // Sum of modifier prices
                
                // Special instructions for this item
                $table->text('notes')->nullable();
                
                $table->timestamps();
                
                // Indexes
                $table->index(['order_id', 'product_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
