<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Domain\Billing\Enums\AiCreditEntryType;
use App\Domain\Billing\Services\AiCreditAlertService;
use App\Domain\Billing\Services\AiCreditLedger;
use App\Domain\Billing\Services\AiCreditPricingService;
use App\Domain\Billing\Services\AiCreditUsageService;
use App\Domain\Billing\Services\PlanEntitlementService;
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
class BudgetEnforcer
{
    private readonly AiCreditLedger $ledger;

    private readonly AiCreditUsageService $usage;

    private readonly AiCreditAlertService $alerts;

    private readonly AiCreditPricingService $pricing;

    public function __construct(
        ?AiCreditLedger $ledger = null,
        ?AiCreditUsageService $usage = null,
        ?AiCreditAlertService $alerts = null,
        ?AiCreditPricingService $pricing = null,
    ) {
        $this->ledger = $ledger ?? new AiCreditLedger;
        $this->usage = $usage ?? new AiCreditUsageService(new PlanEntitlementService);
        $this->alerts = $alerts ?? new AiCreditAlertService($this->usage);
        $this->pricing = $pricing ?? new AiCreditPricingService;
    }

    /**
     * Reserva créditos estimados ANTES de ejecutar operación IA.
     *
     * @param  string  $tenantId  UUID del tenant
     * @param  float  $estimatedCost  Créditos comerciales estimados y autorizados
     * @param  string  $operation  Nombre de la operación para auditoría
     * @return string ID de reserva para tracking
     *
     * @throws InsufficientAiCreditsException Si no hay créditos disponibles
     */
    public function reserve(string $tenantId, float $estimatedCost, string $operation): string
    {
        $this->assertPositiveMoney($estimatedCost, 'estimatedCost');
        $reservationId = bin2hex(random_bytes(16));

        return DB::transaction(function () use ($tenantId, $estimatedCost, $operation, $reservationId) {
            // ═══════════════════════════════════════════════════════════════════
            // BLOQUEO PESIMISTA: Serializa todas las operaciones de este tenant
            // ═══════════════════════════════════════════════════════════════════
            /** @var Tenant $tenant */
            $tenant = Tenant::lockForUpdate()->find($tenantId);

            if (! $tenant) {
                throw new \RuntimeException("Tenant {$tenantId} no encontrado");
            }

            $this->usage->assertWithinDailyLimit($tenant, $estimatedCost);

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

            DB::table('ai_credit_reservations')->insert([
                'id' => $reservationId,
                'tenant_id' => (string) $tenantId,
                'operation' => mb_substr($operation, 0, 100),
                'estimated_cost' => $estimatedCost,
                'denomination' => 'commercial_credits',
                'pricing_version' => $this->pricing->version(),
                'credits_per_provider_usd' => $this->pricing->creditsPerProviderUsd(),
                'minimum_charge' => $this->pricing->minimumCharge(),
                'status' => 'pending',
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);
            $this->ledger->append(
                tenantId: $tenantId,
                type: AiCreditEntryType::RESERVATION_CREATED,
                balanceDelta: 0,
                reservedDelta: $estimatedCost,
                idempotencyKey: "reservation:{$reservationId}:created",
                reservationId: $reservationId,
                billingPeriod: now()->utc()->format('Y-m'),
                metadata: ['operation' => mb_substr($operation, 0, 100)],
            );

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

    /** @return array{version:string,credits_per_provider_usd:float,minimum_charge:float} */
    public function pricingSnapshot(string $tenantId, string $reservationId): array
    {
        $snapshot = DB::table('ai_credit_reservations')
            ->where('id', $reservationId)
            ->where('tenant_id', $tenantId)
            ->where('denomination', 'commercial_credits')
            ->first(['pricing_version', 'credits_per_provider_usd', 'minimum_charge']);

        if (
            $snapshot === null
            || ! is_string($snapshot->pricing_version)
            || $snapshot->pricing_version === ''
            || ! is_numeric($snapshot->credits_per_provider_usd)
            || ! is_numeric($snapshot->minimum_charge)
        ) {
            throw new \LogicException('AI credit reservation pricing snapshot is unavailable.');
        }

        $rate = (float) $snapshot->credits_per_provider_usd;
        $minimum = (float) $snapshot->minimum_charge;
        $this->pricing->fromProviderUsdAtSnapshot(0.0, $rate, $minimum);

        return [
            'version' => $snapshot->pricing_version,
            'credits_per_provider_usd' => $rate,
            'minimum_charge' => $minimum,
        ];
    }

    /**
     * Liquida la reserva después de operación exitosa.
     *
     * @param  string  $tenantId  UUID del tenant
     * @param  float  $estimatedCost  Costo estimado original (para liberar reserva)
     * @param  float  $actualCost  Créditos comerciales calculados desde costo interno versionado
     * @param  string  $reservationId  ID de reserva original
     * @param  array  $usageMetadata  Metadata de uso para auditoría
     */
    public function settle(
        string $tenantId,
        float $estimatedCost,
        float $actualCost,
        string $reservationId,
        array $usageMetadata = []
    ): void {
        $this->assertPositiveMoney($estimatedCost, 'estimatedCost');
        $this->assertNonNegativeMoney($actualCost, 'actualCost');

        DB::transaction(function () use ($tenantId, $estimatedCost, $actualCost, $reservationId, $usageMetadata) {
            $reservation = DB::table('ai_credit_reservations')
                ->where('id', $reservationId)
                ->where('tenant_id', (string) $tenantId)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new \RuntimeException('AI credit reservation not found.');
            }

            if ($reservation->status === 'settled') {
                return;
            }

            if ($reservation->status !== 'pending') {
                throw new \LogicException('Only pending AI credit reservations can be settled.');
            }

            $persistedEstimate = (float) $reservation->estimated_cost;
            if (abs($persistedEstimate - $estimatedCost) > 0.000001) {
                throw new \InvalidArgumentException('AI credit reservation amount mismatch.');
            }

            /** @var Tenant $tenant */
            $tenant = Tenant::lockForUpdate()->find($tenantId);

            if (! $tenant) {
                throw new \RuntimeException("Tenant {$tenantId} no encontrado durante settle");
            }

            // ═══════════════════════════════════════════════════════════════════
            // LIQUIDAR: Mover de reservado a gastado realmente
            // ═══════════════════════════════════════════════════════════════════

            // Never charge beyond the amount authorized before the provider call.
            // Estimation overruns are absorbed by the platform and remain visible in provider metadata.
            $chargedCost = min($actualCost, $persistedEstimate);

            // 1. Liberar la reserva completa
            $tenant->ai_reserved_balance = max(0, (float) $tenant->ai_reserved_balance - $persistedEstimate);

            // 2. Decrementar el balance real por el costo efectivo
            $tenant->ai_balance = (float) $tenant->ai_balance - $chargedCost;

            $tenant->save();

            DB::table('ai_credit_reservations')
                ->where('id', $reservationId)
                ->update([
                    'actual_cost' => $chargedCost,
                    'status' => 'settled',
                    'usage_metadata' => json_encode($usageMetadata, JSON_THROW_ON_ERROR),
                    'settled_at' => now()->utc(),
                    'updated_at' => now()->utc(),
                ]);
            $this->ledger->append(
                tenantId: $tenantId,
                type: AiCreditEntryType::RESERVATION_SETTLED,
                balanceDelta: -$chargedCost,
                reservedDelta: -$persistedEstimate,
                idempotencyKey: "reservation:{$reservationId}:settled",
                reservationId: $reservationId,
                billingPeriod: now()->utc()->format('Y-m'),
                metadata: [
                    'estimated_cost' => $persistedEstimate,
                    'actual_cost' => $chargedCost,
                ],
            );
            $this->alerts->recordCrossedThresholds($tenant);

            // Calcular diferencia para auditoría
            $difference = $estimatedCost - $chargedCost;

            Log::info('✅ AI Credits settled', [
                'tenant_id' => $tenantId,
                'reservation_id' => $reservationId,
                'estimated_cost' => $estimatedCost,
                'actual_cost' => $chargedCost,
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
     * @param  string  $tenantId  UUID del tenant
     * @param  float  $estimatedCost  Costo estimado que se reservó
     * @param  string  $reservationId  ID de reserva original
     * @param  string  $failureReason  Razón del fallo para auditoría
     */
    public function refund(
        string $tenantId,
        float $estimatedCost,
        string $reservationId,
        string $failureReason
    ): void {
        $this->assertPositiveMoney($estimatedCost, 'estimatedCost');

        DB::transaction(function () use ($tenantId, $estimatedCost, $reservationId, $failureReason) {
            $reservation = DB::table('ai_credit_reservations')
                ->where('id', $reservationId)
                ->where('tenant_id', (string) $tenantId)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new \RuntimeException('AI credit reservation not found.');
            }

            if ($reservation->status === 'refunded') {
                return;
            }

            if ($reservation->status !== 'pending') {
                throw new \LogicException('Only pending AI credit reservations can be refunded.');
            }

            $persistedEstimate = (float) $reservation->estimated_cost;
            if (abs($persistedEstimate - $estimatedCost) > 0.000001) {
                throw new \InvalidArgumentException('AI credit reservation amount mismatch.');
            }

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
            $tenant->ai_reserved_balance = max(0, (float) $tenant->ai_reserved_balance - $persistedEstimate);
            $tenant->save();

            DB::table('ai_credit_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => 'refunded',
                    'failure_reason' => $this->safeFailureCode($failureReason),
                    'refunded_at' => now()->utc(),
                    'updated_at' => now()->utc(),
                ]);
            $this->ledger->append(
                tenantId: $tenantId,
                type: AiCreditEntryType::RESERVATION_REFUNDED,
                balanceDelta: 0,
                reservedDelta: -$persistedEstimate,
                idempotencyKey: "reservation:{$reservationId}:refunded",
                reservationId: $reservationId,
                billingPeriod: now()->utc()->format('Y-m'),
                metadata: ['failure_code' => $this->safeFailureCode($failureReason)],
            );

            Log::warning('🔄 AI Credits refunded due to operation failure', [
                'tenant_id' => $tenantId,
                'reservation_id' => $reservationId,
                'refunded_amount' => $estimatedCost,
                'failure_code' => $this->safeFailureCode($failureReason),
                'new_reserved_balance' => $tenant->ai_reserved_balance,
            ]);
        }, 3);
    }

    /**
     * Obtiene el balance disponible del tenant (balance - reservado).
     */
    public function getAvailableBalance(string $tenantId): float
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return 0.0;
        }

        return max(0, (float) $tenant->ai_balance - (float) $tenant->ai_reserved_balance);
    }

    private function assertPositiveMoney(float $amount, string $field): void
    {
        if (! is_finite($amount) || $amount <= 0) {
            throw new \InvalidArgumentException("{$field} must be a positive finite amount.");
        }
    }

    private function assertNonNegativeMoney(float $amount, string $field): void
    {
        if (! is_finite($amount) || $amount < 0) {
            throw new \InvalidArgumentException("{$field} must be a non-negative finite amount.");
        }
    }

    private function safeFailureCode(string $reason): string
    {
        $normalized = strtolower(trim($reason));

        return preg_match('/^[a-z0-9][a-z0-9_:-]{0,99}$/', $normalized) === 1
            ? $normalized
            : 'provider_failure';
    }
}
