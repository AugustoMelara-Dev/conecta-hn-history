<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exceptions\InsufficientAiCreditsException;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BudgetEnforcer - Servicio de Presupuesto IA Atómico
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PATRÓN: RESERVE-EXECUTE-SETTLE (Prevención de EDoS)                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  FLUJO:                                                                  ║
 * ║    1. reserve(): Bloquea créditos estimados antes del API call         ║
 * ║    2. [Caller ejecuta API de OpenAI]                                    ║
 * ║    3. settle(): Confirma el costo real y libera exceso reservado       ║
 * ║                                                                          ║
 * ║  BENEFICIOS:                                                             ║
 * ║    - Previene "doble gasto" en solicitudes concurrentes                 ║
 * ║    - Protege contra EDoS (factura infinita antes de validar)            ║
 * ║    - Rollback automático si la solicitud falla                          ║
 * ║                                                                          ║
 * ║  BLOQUEO PESIMISTA:                                                      ║
 * ║    Usa lockForUpdate() para serializar acceso al saldo del tenant       ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class BudgetEnforcer
{
    /**
     * Reserva créditos estimados ANTES de ejecutar operación IA.
     *
     * @param  int|string $tenantId  ID del tenant (UUID o int)
     * @param  float $estimatedCost  Costo estimado en USD (con margen de seguridad)
     * @param  string $operation     Nombre de la operación para auditoría
     * @return string                ID de reserva para tracking
     *
     * @throws InsufficientAiCreditsException Si no hay créditos disponibles
     */
    public function reserve(int|string $tenantId, float $estimatedCost, string $operation): string
    {
        $reservationId = bin2hex(random_bytes(8)); // 16-char hex ID

        return DB::transaction(function () use ($tenantId, $estimatedCost, $operation, $reservationId) {
            // ═══════════════════════════════════════════════════════════════════
            // BLOQUEO PESIMISTA: Serializa todas las operaciones de este tenant
            // ═══════════════════════════════════════════════════════════════════
            /** @var Tenant $tenant */
            $tenant = Tenant::lockForUpdate()->find($tenantId);

            if (! $tenant) {
                throw new \RuntimeException("Tenant {$tenantId} no encontrado");
            }

            // Balance disponible = Balance confirmado - Balance ya reservado
            $availableBalance = (float) $tenant->ai_balance - (float) $tenant->ai_reserved_balance;

            if ($availableBalance < $estimatedCost) {
                Log::channel('security')->warning('🚫 AI Credit reservation failed - Insufficient balance', [
                    'tenant_id' => $tenantId,
                    'operation' => $operation,
                    'estimated_cost' => $estimatedCost,
                    'available_balance' => $availableBalance,
                    'reservation_id' => $reservationId,
                ]);

                throw InsufficientAiCreditsException::forOperation(
                    tenantId: $tenantId,
                    operation: $operation,
                    estimatedCost: $estimatedCost,
                    availableBalance: $availableBalance,
                );
            }

            // ═══════════════════════════════════════════════════════════════════
            // RESERVAR: Incrementar balance reservado (no tocar ai_balance aún)
            // ═══════════════════════════════════════════════════════════════════
            $tenant->ai_reserved_balance = (float) $tenant->ai_reserved_balance + $estimatedCost;
            $tenant->save();

            Log::info('💰 AI Credits reserved', [
                'tenant_id' => $tenantId,
                'operation' => $operation,
                'reserved_amount' => $estimatedCost,
                'reservation_id' => $reservationId,
                'new_reserved_balance' => $tenant->ai_reserved_balance,
            ]);

            return $reservationId;
        }, 3); // 3 intentos de deadlock retry
    }

    /**
     * Liquida la reserva después de operación exitosa.
     *
     * @param  int|string $tenantId   ID del tenant (UUID o int)
     * @param  float  $estimatedCost  Costo estimado original (para liberar reserva)
     * @param  float  $actualCost     Costo real reportado por OpenAI
     * @param  string $reservationId  ID de reserva original
     * @param  array  $usageMetadata  Metadata de uso para auditoría
     */
    public function settle(
        int|string $tenantId,
        float $estimatedCost,
        float $actualCost,
        string $reservationId,
        array $usageMetadata = []
    ): void {
        DB::transaction(function () use ($tenantId, $estimatedCost, $actualCost, $reservationId, $usageMetadata) {
            /** @var Tenant $tenant */
            $tenant = Tenant::lockForUpdate()->find($tenantId);

            if (! $tenant) {
                throw new \RuntimeException("Tenant {$tenantId} no encontrado durante settle");
            }

            // ═══════════════════════════════════════════════════════════════════
            // LIQUIDAR: Mover de reservado a gastado realmente
            // ═══════════════════════════════════════════════════════════════════

            // 1. Liberar la reserva completa
            $tenant->ai_reserved_balance = max(0, (float) $tenant->ai_reserved_balance - $estimatedCost);

            // 2. Decrementar el balance real por el costo efectivo
            $tenant->ai_balance = max(0, (float) $tenant->ai_balance - $actualCost);

            $tenant->save();

            // Calcular diferencia para auditoría
            $difference = $estimatedCost - $actualCost;

            Log::info('✅ AI Credits settled', [
                'tenant_id' => $tenantId,
                'reservation_id' => $reservationId,
                'estimated_cost' => $estimatedCost,
                'actual_cost' => $actualCost,
                'refunded_difference' => $difference,
                'new_balance' => $tenant->ai_balance,
                'new_reserved_balance' => $tenant->ai_reserved_balance,
                ...$usageMetadata,
            ]);
        }, 3);
    }

    /**
     * Reembolsa una reserva si la operación falló.
     *
     * @param  int|string $tenantId   ID del tenant (UUID o int)
     * @param  float  $estimatedCost  Costo estimado que se reservó
     * @param  string $reservationId  ID de reserva original
     * @param  string $failureReason  Razón del fallo para auditoría
     */
    public function refund(
        int|string $tenantId,
        float $estimatedCost,
        string $reservationId,
        string $failureReason
    ): void {
        DB::transaction(function () use ($tenantId, $estimatedCost, $reservationId, $failureReason) {
            /** @var Tenant $tenant */
            $tenant = Tenant::lockForUpdate()->find($tenantId);

            if (! $tenant) {
                Log::error('Tenant not found during refund', [
                    'tenant_id' => $tenantId,
                    'reservation_id' => $reservationId,
                ]);
                return;
            }

            // ═══════════════════════════════════════════════════════════════════
            // REEMBOLSO: Simplemente liberar la reserva (el balance nunca cambió)
            // ═══════════════════════════════════════════════════════════════════
            $tenant->ai_reserved_balance = max(0, (float) $tenant->ai_reserved_balance - $estimatedCost);
            $tenant->save();

            Log::warning('🔄 AI Credits refunded due to operation failure', [
                'tenant_id' => $tenantId,
                'reservation_id' => $reservationId,
                'refunded_amount' => $estimatedCost,
                'failure_reason' => $failureReason,
                'new_reserved_balance' => $tenant->ai_reserved_balance,
            ]);
        }, 3);
    }

    /**
     * Obtiene el balance disponible del tenant (balance - reservado).
     */
    public function getAvailableBalance(int|string $tenantId): float
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return 0.0;
        }

        return max(0, (float) $tenant->ai_balance - (float) $tenant->ai_reserved_balance);
    }
}
