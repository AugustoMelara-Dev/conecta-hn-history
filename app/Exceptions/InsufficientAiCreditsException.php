<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * InsufficientAiCreditsException - Fondos Insuficientes para Operación IA
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HTTP 402 PAYMENT REQUIRED - Semántica Correcta para Créditos IA       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  DIFERENCIA CON 403 FORBIDDEN:                                          ║
 * ║    - 403: Acceso denegado permanentemente (permisos)                    ║
 * ║    - 402: Acceso denegado temporalmente (fondos insuficientes)          ║
 * ║                                                                          ║
 * ║  USO:                                                                    ║
 * ║    Lanzada por BudgetEnforcer::reserve() cuando el tenant no tiene     ║
 * ║    suficiente ai_balance disponible para la operación solicitada.       ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class InsufficientAiCreditsException extends DomainException
{
    public function __construct(
        string $message = 'Créditos de IA insuficientes para esta operación',
        private readonly float $requiredCredits = 0.0,
        private readonly float $availableCredits = 0.0,
        private readonly array $contextData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function context(): array
    {
        return [
            'required_credits' => $this->requiredCredits,
            'available_credits' => $this->availableCredits,
            ...$this->contextData,
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 402; // Payment Required
    }

    public function getErrorCode(): string
    {
        return 'INSUFFICIENT_AI_CREDITS';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Factory Methods
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Factory: Crear para operación de generación de perfiles
     */
    public static function forProfileGeneration(
        string $tenantId,
        float $estimatedCost,
        float $availableBalance
    ): self {
        return new self(
            message: "Créditos IA insuficientes. Requerido: {$estimatedCost} créditos, disponible: {$availableBalance}.",
            requiredCredits: $estimatedCost,
            availableCredits: $availableBalance,
            contextData: [
                'tenant_id' => $tenantId,
                'operation' => 'profile_generation',
            ],
        );
    }

    /**
     * Factory: Crear para operación genérica de IA
     */
    public static function forDailyLimit(
        string $tenantId,
        float $requiredCredits,
        float $remainingDailyCredits,
        float $dailyLimit,
    ): self {
        return new self(
            message: 'Alcanzaste el limite diario de IA. El consumo se restablece al iniciar el siguiente dia UTC.',
            requiredCredits: $requiredCredits,
            availableCredits: $remainingDailyCredits,
            contextData: [
                'tenant_id' => $tenantId,
                'reason' => 'daily_limit',
                'daily_limit' => $dailyLimit,
            ],
        );
    }

    public static function forOperation(
        string $tenantId,
        string $operation,
        float $estimatedCost,
        float $availableBalance
    ): self {
        return new self(
            message: "Créditos IA insuficientes para '{$operation}'. Requerido: {$estimatedCost} créditos, disponible: {$availableBalance}.",
            requiredCredits: $estimatedCost,
            availableCredits: $availableBalance,
            contextData: [
                'tenant_id' => $tenantId,
                'operation' => $operation,
            ],
        );
    }
}
