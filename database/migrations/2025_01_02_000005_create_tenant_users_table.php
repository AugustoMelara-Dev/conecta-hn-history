<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * USUARIOS DEL TENANT - Conecta HN
     * Sistema de roles multi-tenant para delegar acceso (Dueño, Gerente, Empleado)
     */
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // ═══ ROL DENTRO DEL TENANT ═══
            $table->enum('role', [
                'owner',      // Dueño - Control total, no se puede eliminar
                'manager',    // Gerente - Casi todo, sin facturación
                'employee'    // Empleado - Solo operaciones básicas
            ])->default('employee');
            
            // ═══ PERMISOS GRANULARES ═══
            // Permiten sobreescribir las restricciones del rol
            $table->boolean('can_manage_products')->default(true);
            $table->boolean('can_manage_orders')->default(true);
            $table->boolean('can_view_analytics')->default(false);
            $table->boolean('can_manage_settings')->default(false);
            $table->boolean('can_manage_team')->default(false);
            $table->boolean('can_manage_billing')->default(false);
            
            // ═══ ESTADO DE INVITACIÓN ═══
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('invitation_token', 64)->nullable();
            
            // ═══ METADATA ═══
            $table->string('invited_by')->nullable();  // Email de quien invitó
            $table->timestamp('last_access_at')->nullable();
            
            $table->timestamps();
            
            // ═══ RESTRICCIONES ═══
            $table->unique(['tenant_id', 'user_id']);
            
            // ═══ ÍNDICES ═══
            $table->index(['tenant_id', 'role']);
            $table->index('invitation_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
