<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product Modifiers Migration
 * 
 * Creates tables for product customization system:
 * - modifier_groups: Groups of options (Size, Extras, Toppings)
 * - modifier_options: Individual options within groups
 * 
 * EXAMPLE USAGE:
 * Product: "Pizza Margarita"
 * └── ModifierGroup: "Tamaño"
 *     ├── Option: "Pequeña" (+L.0)
 *     ├── Option: "Mediana" (+L.50)
 *     └── Option: "Grande" (+L.100)
 * └── ModifierGroup: "Extras"
 *     ├── Option: "Pepperoni" (+L.25)
 *     └── Option: "Champiñones" (+L.20)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // MODIFIER GROUPS: Categories of customization options
        // ═══════════════════════════════════════════════════════════════════════
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // Group identity
            $table->string('name');  // "Tamaño", "Extras", "Salsa"
            $table->string('description')->nullable();
            
            // Selection rules
            $table->boolean('is_required')->default(false);  // Must select one?
            $table->unsignedTinyInteger('min_selections')->default(0);  // Min options to select
            $table->unsignedTinyInteger('max_selections')->default(1);  // Max options to select
            
            // Display
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'sort_order']);
        });

        // ═══════════════════════════════════════════════════════════════════════
        // MODIFIER OPTIONS: Individual choices within a group
        // ═══════════════════════════════════════════════════════════════════════
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->onDelete('cascade');
            
            // Option identity
            $table->string('name');  // "Grande", "Pepperoni", "Sin cebolla"
            
            // Pricing
            $table->decimal('price_adjustment', 10, 2)->default(0);  // +L.50, -L.10, etc.
            
            // Availability
            $table->boolean('is_available')->default(true);
            $table->boolean('is_default')->default(false);  // Pre-selected option
            
            // Display
            $table->unsignedSmallInteger('sort_order')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['modifier_group_id', 'is_available']);
            $table->index(['modifier_group_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
    }
};
