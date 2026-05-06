<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: AI Budget Columns para Patrón Reserve-Execute-Settle
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PARCHE DE SEGURIDAD: Prevención de EDoS (Economic Denial of Service)   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  COLUMNAS:                                                               ║
 * ║    - ai_balance: Créditos confirmados y liquidados disponibles          ║
 * ║    - ai_reserved_balance: Créditos bloqueados en solicitudes pendientes ║
 * ║                                                                          ║
 * ║  ARQUITECTURA:                                                           ║
 * ║    La separación de "Balance" y "Reservado" permite:                    ║
 * ║    1. Auditar transacciones "zombi" si un worker falla                  ║
 * ║    2. Prevenir ataques de "doble gasto" mediante bloqueo pesimista      ║
 * ║    3. Rollback automático de reservas en caso de fallo de API           ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Balance confirmado de créditos IA (USD con 6 decimales para micro-costos)
            $table->decimal('ai_balance', 10, 6)
                ->default(0.000000)
                ->after('plan')
                ->comment('Créditos IA confirmados disponibles (USD)');

            // Balance reservado - créditos bloqueados en solicitudes activas
            $table->decimal('ai_reserved_balance', 10, 6)
                ->default(0.000000)
                ->after('ai_balance')
                ->comment('Créditos IA bloqueados en solicitudes pendientes (USD)');

            // Índice para consultas de auditoría de saldos
            $table->index(['ai_balance', 'ai_reserved_balance'], 'idx_tenants_ai_budget');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('idx_tenants_ai_budget');
            $table->dropColumn(['ai_balance', 'ai_reserved_balance']);
        });
    }
};
