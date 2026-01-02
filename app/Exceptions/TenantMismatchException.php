<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * TenantMismatchException - Security Exception for Cross-Tenant Access Attempts
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY: Thrown when tenant context doesn't match expected tenant     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  SCENARIOS:                                                             ║
 * ║    1. Queue worker processes event with stale tenant context           ║
 * ║    2. Model not found with event's tenant_id scope                     ║
 * ║    3. Manual tenant context manipulation attempted                      ║
 * ║                                                                          ║
 * ║  LOGGING:                                                               ║
 * ║    This exception should ALWAYS be logged at CRITICAL level            ║
 * ║    and potentially trigger security alerting (SIEM integration).       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class TenantMismatchException extends DomainException
{
    public function __construct(
        string $message = 'Conflicto de contexto de acceso a datos detectado',
        private readonly ?int $expectedTenantId = null,
        private readonly ?int $actualTenantId = null,
        private readonly array $contextData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->validateContextSafety($contextData);
    }

    public function context(): array
    {
        return [
            'expected_tenant_id' => $this->expectedTenantId,
            'actual_tenant_id' => $this->actualTenantId,
            'severity' => 'CRITICAL',
            ...$this->contextData,
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 403; // Forbidden
    }

    public function getErrorCode(): string
    {
        return 'TENANT_MISMATCH';
    }

    public function expectedTenantId(): ?int
    {
        return $this->expectedTenantId;
    }

    public function actualTenantId(): ?int
    {
        return $this->actualTenantId;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Factory Methods
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Factory: Create for order tenant mismatch.
     */
    public static function forOrder(int $orderId, int $expectedTenantId, ?int $actualTenantId = null): self
    {
        $actual = $actualTenantId !== null ? "actual: {$actualTenantId}" : 'no encontrado';

        return new self(
            message: "Conflicto de acceso: La Orden {$orderId} no pertenece al contexto esperado ({$expectedTenantId}). Contexto actual: {$actual}",
            expectedTenantId: $expectedTenantId,
            actualTenantId: $actualTenantId,
            contextData: ['order_id' => $orderId, 'resource_type' => 'Order'],
        );
    }

    /**
     * Factory: Create for generic resource tenant mismatch.
     */
    public static function forResource(string $resource, int $resourceId, int $expectedTenantId, ?int $actualTenantId = null): self
    {
        return new self(
            message: "Recurso {$resource} {$resourceId} no encontrado para el negocio {$expectedTenantId}",
            expectedTenantId: $expectedTenantId,
            actualTenantId: $actualTenantId,
            contextData: ['resource_type' => $resource, 'resource_id' => $resourceId],
        );
    }

    /**
     * Factory: Create for cross-tenant access attempt.
     */
    public static function crossTenantAccess(int $requestingTenantId, int $resourceTenantId, string $resourceType, int $resourceId): self
    {
        return new self(
            message: "Acceso cruzado denegado: Negocio {$requestingTenantId} intentó acceder a {$resourceType} {$resourceId} propiedad de {$resourceTenantId}",
            expectedTenantId: $resourceTenantId,
            actualTenantId: $requestingTenantId,
            contextData: [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'access_violation' => true,
            ],
        );
    }
}
