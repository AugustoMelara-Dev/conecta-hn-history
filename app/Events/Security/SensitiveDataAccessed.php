<?php

declare(strict_types=1);

namespace App\Events\Security;

use App\Contracts\Events\AuditEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Enums\Severity;
use App\ValueObjects\AuditContext;

/**
 * SensitiveDataAccessed - Compliance Event for Data Access Logging
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  COMPLIANCE: PCI-DSS / GDPR Audit Trail                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS REPRESENTED:                                                ║
 * ║    - A privileged user accessed sensitive data                          ║
 * ║    - The access was logged with immutable actor identity                ║
 * ║    - The resource and action are recorded for forensics                 ║
 * ║                                                                          ║
 * ║  ORM-AGNOSTIC DESIGN:                                                   ║
 * ║    - ❌ NO User model (snapshotted as primitives)                       ║
 * ║    - ❌ NO ShouldBroadcast (security risk)                              ║
 * ║    - ✅ Actor identity preserved even if user deleted                   ║
 * ║    - ✅ Synchronous execution for audit integrity                       ║
 * ║                                                                          ║
 * ║  TRIGGERED WHEN:                                                        ║
 * ║    - Admin exports customer data                                        ║
 * ║    - Payment details viewed                                             ║
 * ║    - PII (Personal Identifiable Information) accessed                   ║
 * ║    - Bulk data download initiated                                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REQUIRED FOR:
 *   - GDPR Article 30 (Records of processing activities)
 *   - PCI-DSS Requirement 10 (Track access to cardholder data)
 */
final readonly class SensitiveDataAccessed implements AuditEvent
{
    /**
     * @param  int  $actorId  The user ID who accessed the data (snapshotted)
     * @param  string  $actorEmail  The user's email (snapshotted for persistence)
     * @param  TenantId  $tenantId  The tenant context
     * @param  string  $resource  The resource type accessed (e.g., 'customer_export')
     * @param  string  $action  The action performed (e.g., 'download', 'view')
     * @param  AuditContext  $context  Standardized audit metadata
     * @param  string|null  $reason  User-provided reason (for compliance)
     * @param  int|null  $affectedRecords  Number of records accessed
     */
    public function __construct(
        public int $actorId,
        public string $actorEmail,
        public TenantId $tenantId,
        public string $resource,
        public string $action,
        private AuditContext $context,
        public ?string $reason = null,
        public ?int $affectedRecords = null,
    ) {}

    /**
     * Factory: Create from authenticated user context.
     */
    public static function create(
        int $userId,
        string $userEmail,
        string $tenantId,
        string $resource,
        string $action,
        ?string $reason = null,
        ?int $affectedRecords = null
    ): self {
        return new self(
            actorId: $userId,
            actorEmail: $userEmail,
            tenantId: TenantId::fromString($tenantId),
            resource: $resource,
            action: $action,
            context: AuditContext::capture(actorId: $userId, anonymizeIp: true),
            reason: $reason,
            affectedRecords: $affectedRecords,
        );
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
            'event' => 'sensitive_data_accessed',
            'actor_id' => $this->actorId,
            'actor_email' => $this->actorEmail,
            'tenant_id' => $this->tenantId->toString(),
            'resource' => $this->resource,
            'action' => $this->action,
            'reason' => $this->reason,
            'affected_records' => $this->affectedRecords,
            ...$safeContext->toArray(anonymize: true),
        ];
    }

    /**
     * Get the PSR-3 severity level.
     */
    public function severity(): Severity
    {
        if ($this->affectedRecords !== null && $this->affectedRecords > 100) {
            return Severity::WARNING;
        }

        return Severity::NOTICE;
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
            'actor_id' => $this->actorId,
            'actor_email' => $this->actorEmail,
            'tenant_id' => $this->tenantId->toString(),
            'resource' => $this->resource,
            'action' => $this->action,
            'reason' => $this->reason,
            'affected_records' => $this->affectedRecords,
            'context' => $this->context->toArray(anonymize: false),
        ];
    }

    public static function fromPrimitives(array $data): self
    {
        // Defensive deserialization: tenant_id is required for multi-tenant context
        $tenantId = $data['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing required key: tenant_id');
        }

        return new self(
            actorId: $data['actor_id'] ?? 0,
            actorEmail: $data['actor_email'] ?? 'unknown@unknown.com',
            tenantId: TenantId::fromSerialized($tenantId),
            resource: $data['resource'] ?? 'unknown',
            action: $data['action'] ?? 'unknown',
            context: AuditContext::fromPrimitives($data['context'] ?? []),
            reason: $data['reason'] ?? null,
            affectedRecords: $data['affected_records'] ?? null,
        );
    }
}
