<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty System Database Schema
 * 
 * Multi-tenant loyalty program with:
 * - Tenant-scoped programs (1 program per tenant)
 * - Append-only ledger (earn/redeem/expire transactions)
 * - Redeemable rewards catalog
 * 
 * INVARIANTS:
 * - Points stored as INTEGER (no floating point math)
 * - Ledger is APPEND-ONLY (insert corrections, never update)
 * - All tables tenant-isolated via tenant_id FK
 * 
 * FK TYPES:
 * - tenants.id = UUID (char 36)
 * - users.id = UUID (char 36)
 * - orders.id = bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // LOYALTY PROGRAMS - Tenant Configuration
        // ═══════════════════════════════════════════════════════════════════════
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36); // UUID FK
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name')->default('Programa de Puntos');
            $table->integer('earn_ratio')->default(10); // Spend L.10 = 1 Point
            $table->integer('redemption_ratio')->default(100); // 100 Points = L.1
            $table->integer('expiry_days')->default(365);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('tenant_id'); // One program per tenant
        });

        // ═══════════════════════════════════════════════════════════════════════
        // LOYALTY LEDGER - Transaction History (Append-Only)
        // ═══════════════════════════════════════════════════════════════════════
        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->id();
            
            // Tenant FK (UUID - char 36)
            $table->char('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            
            // User FK (UUID - char 36)
            $table->char('user_id', 36);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            
            // Order FK (bigint, nullable)
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            
            $table->string('type'); // 'earn', 'redeem', 'expire', 'adjustment'
            $table->integer('amount'); // Positive (earn) or Negative (redeem/expire)
            $table->string('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            // Fast balance lookup index
            $table->index(['tenant_id', 'user_id']);
            $table->index(['user_id', 'expires_at']);
        });

        // ═══════════════════════════════════════════════════════════════════════
        // LOYALTY REWARDS - Redeemable Catalog
        // ═══════════════════════════════════════════════════════════════════════
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36); // UUID FK
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('points_cost');
            $table->integer('stock')->default(-1); // -1 = unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('loyalty_ledger');
        Schema::dropIfExists('loyalty_programs');
    }
};
