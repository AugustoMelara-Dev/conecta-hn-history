<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons Table Migration
 * 
 * Promotional discounts with:
 * - Multi-tenant isolation (tenant_id)
 * - Two discount types: fixed amount or percentage
 * - Business rules: min_spend, usage_limit, date range
 * - Atomic usage tracking for race condition prevention
 * 
 * UNIQUE CONSTRAINT: code is unique per tenant (different tenants can have same code)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // ═══════════════════════════════════════════════════════════════════════
            // COUPON IDENTIFICATION
            // ═══════════════════════════════════════════════════════════════════════
            // Uppercase code for easy matching (transformed on save)
            $table->string('code', 50);
            $table->string('name')->nullable(); // Human-readable name for admin
            $table->text('description')->nullable();
            
            // ═══════════════════════════════════════════════════════════════════════
            // DISCOUNT DEFINITION
            // ═══════════════════════════════════════════════════════════════════════
            // 'fixed' = exact amount off (L. 50 discount)
            // 'percent' = percentage off (10% discount)
            $table->enum('type', ['fixed', 'percent']);
            
            // For fixed: the amount to subtract
            // For percent: the percentage (e.g., 10 for 10%)
            $table->decimal('value', 10, 2);
            
            // Maximum discount for percent type (null = no cap)
            // Prevents 50% off on a L.10,000 order becoming L.5,000 discount
            $table->decimal('max_discount', 10, 2)->nullable();
            
            // ═══════════════════════════════════════════════════════════════════════
            // BUSINESS RULES
            // ═══════════════════════════════════════════════════════════════════════
            // Minimum cart subtotal required (null = no minimum)
            $table->decimal('min_spend', 10, 2)->nullable();
            
            // Maximum number of redemptions (null = unlimited)
            $table->unsignedInteger('usage_limit')->nullable();
            
            // Current number of successful redemptions
            // MUST be incremented atomically to prevent race conditions
            $table->unsignedInteger('usage_count')->default(0);
            
            // ═══════════════════════════════════════════════════════════════════════
            // TEMPORAL VALIDITY
            // ═══════════════════════════════════════════════════════════════════════
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Manual on/off toggle
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // ═══════════════════════════════════════════════════════════════════════
            // INDEXES
            // ═══════════════════════════════════════════════════════════════════════
            // Unique code per tenant
            $table->unique(['tenant_id', 'code']);
            
            // Quick lookup by tenant
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
