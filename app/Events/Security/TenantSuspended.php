<?php

declare(strict_types=1);

namespace App\Events\Security;

use App\Contracts\Events\AuditEvent;
use App\Enums\Severity;
use App\ValueObjects\AuditContext;
use App\ValueObjects\TenantId;
use DateTimeImmutable;

/**
 * TenantSuspended - Platform Security Event for Tenant Lifecycle
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PLATFORM SECURITY: Tenant Lifecycle Audit                              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS REPRESENTED:                                                ║
 * ║    - A tenant's access has been revoked                                 ║
 * ║    - The suspension was authorized (manually or by system)             ║
 * ║    - Tenant data is preserved for potential restoration                ║
 * ║                                                                          ║
 * ║  ORM-AGNOSTIC DESIGN:                                                   ║
 * ║    - ❌ NO Tenant model (snapshotted as primitives)                     ║
 * ║    - ❌ NO ShouldBroadcast (security risk)                              ║
 * ║    - ✅ Tenant identity preserved even if tenant deleted               ║
 * ║    - ✅ Synchronous execution for audit integrity                       ║
 * ║                                                                          ║
 * ║  TRIGGERED WHEN:                                                        ║
 * ║    - Subscription expired and not renewed                               ║
 * ║    - Terms of Service violation detected                               ║
 * ║    - Manual suspension by platform admin                               ║
 * ║    - Fraudulent activity suspected                                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class TenantSuspended implements AuditEvent
{
    /**
     * @param  TenantId  $tenantId  The suspended tenant's ID
     * @param  string  $tenantSlug  The tenant's slug (snapshotted)
     * @param  string  $tenantName  The tenant's name (snapshotted)
     * @param  string  $reason  Machine or human-readable suspension reason
     * @param  AuditContext  $context  Standardized audit metadata
     * @param  int|null  $suspendedById  The admin user ID who performed suspension
     * @param  DateTimeImmutable|null  $suspendedUntil  Expiration (null = permanent)
     */
    public function __construct(
        public TenantId $tenantId,
        public string $tenantSlug,
        public string $tenantName,
        public string $reason,
        private AuditContext $context,
        public ?int $suspendedById = null,
        public ?DateTimeImmutable $suspendedUntil = null,
    ) {}

    /**
     * Factory: Create from tenant data (snapshotted at creation time).
     */
    public static function create(
        int $tenantId,
        string $tenantSlug,
        string $tenantName,
        string $reason,
        ?int $suspendedById = null,
        ?DateTimeImmutable $suspendedUntil = null
    ): self {
        return new self(
            tenantId: TenantId::fromInt($tenantId),
            tenantSlug: $tenantSlug,
            tenantName: $tenantName,
            reason: $reason,
            context: AuditContext::capture(actorId: $suspendedById, anonymizeIp: true),
            suspendedById: $suspendedById,
            suspendedUntil: $suspendedUntil,
        );
    }

    /**
     * Check if this is a permanent suspension.
     */
    public function isPermanent(): bool
    {
        return $this->suspendedUntil === null;
    }

    /**
     * Check if this was a system-initiated suspension (not manual).
     */
    public function isSystemSuspension(): bool
    {
        return $this->suspendedById === null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AuditEvent Interface Implementation
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Convert to a flat array for secure logging.
     */
    public function toAuditLog(): array
    {
        $safeContext = $this->context->withAnonymizedIp();

        return [
            'event' => 'tenant_suspended',
            'tenant_id' => $this->tenantId->toInt(),
            'tenant_slug' => $this->tenantSlug,
            'tenant_name' => $this->tenantName,
            'reason' => $this->reason,
            'suspended_by_id' => $this->suspendedById,
            'suspended_until' => $this->suspendedUntil?->format(DateTimeImmutable::ATOM),
            'is_permanent' => $this->isPermanent(),
            'is_system' => $this->isSystemSuspension(),
            ...$safeContext->toArray(anonymize: true),
        ];
    }

    /**
     * Get the PSR-3 severity level.
     */
    public function severity(): Severity
    {
        return Severity::WARNING;
    }

    /**
     * Get the standardized audit context.
     */
    public function context(): AuditContext
    {
        return $this->context;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Safe Serialization (Queue-Safe, Refactor-Proof)
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
            'tenant_id' => $this->tenantId->toInt(),
            'tenant_slug' => $this->tenantSlug,
            'tenant_name' => $this->tenantName,
            'reason' => $this->reason,
            'suspended_by_id' => $this->suspendedById,
            'suspended_until' => $this->suspendedUntil?->format(DateTimeImmutable::ATOM),
            'context' => $this->context->toArray(anonymize: false),
        ];
    }

    public static function fromPrimitives(array $data): self
    {
        // Defensive deserialization: tenant_id is required
        $tenantId = $data['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing required key: tenant_id');
        }

        return new self(
            tenantId: TenantId::fromInt($tenantId),
            tenantSlug: $data['tenant_slug'] ?? 'unknown',
            tenantName: $data['tenant_name'] ?? 'Unknown Tenant',
            reason: $data['reason'] ?? 'unknown',
            context: AuditContext::fromPrimitives($data['context'] ?? []),
            suspendedById: $data['suspended_by_id'] ?? null,
            suspendedUntil: isset($data['suspended_until'])
                ? new DateTimeImmutable($data['suspended_until'])
                : null,
        );
    }
}
