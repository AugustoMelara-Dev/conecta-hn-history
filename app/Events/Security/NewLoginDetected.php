<?php

declare(strict_types=1);

namespace App\Events\Security;

use App\Contracts\Events\AuditEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Enums\Severity;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\AuditContext;
use App\ValueObjects\IpAddress;
use DateTimeImmutable;

/**
 * NewLoginDetected - GDPR-Compliant Audit Event for Login Tracking
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY AUDIT: Successful Authentication Tracking                     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS REPRESENTED:                                                ║
 * ║    - A user successfully authenticated                                  ║
 * ║    - The login originated from a specific IP (GDPR-anonymized)          ║
 * ║    - The event is logged for security auditing                         ║
 * ║                                                                          ║
 * ║  GDPR COMPLIANCE:                                                       ║
 * ║    - ❌ NO full IP address logged                                       ║
 * ║    - ❌ NO Eloquent User model (prevents PII leaks)                    ║
 * ║    - ✅ IP anonymized (last octet zeroed: 192.168.1.0)                 ║
 * ║    - ✅ Only user_id and email snapshot stored                         ║
 * ║    - ✅ Immutable audit trail                                          ║
 * ║                                                                          ║
 * ║  PURITY RULES:                                                          ║
 * ║    - ❌ NO Eloquent models                                              ║
 * ║    - ❌ NO ShouldBroadcast (security risk)                              ║
 * ║    - ✅ Synchronous execution (logged immediately)                     ║
 * ║    - ✅ IP anonymized via IpAddress VO                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * LISTENERS:
 *   - LogLoginToAuditTrailListener
 *   - DetectSuspiciousLoginListener
 *   - UpdateLastLoginTimestampListener
 *
 * DISPATCH POINT: AuthenticatedSessionController::store() on successful login
 */
final readonly class NewLoginDetected implements AuditEvent
{
    use HasDomainMetadata;

    /**
     * @param  TenantId|null  $tenantId  Tenant context (null for system-level login)
     * @param  int  $userId  The authenticated user's ID
     * @param  string  $userEmail  User email (snapshot for audit trail)
     * @param  IpAddress  $ipAddress  GDPR-anonymized IP address
     * @param  string  $userAgent  Browser/client user agent string
     * @param  Severity  $severity  Event severity level
     */
    public function __construct(
        private ?TenantId $tenantId,
        private int $userId,
        private string $userEmail,
        private IpAddress $ipAddress,
        private string $userAgent,
        private Severity $severity,
        // Restoration parameters for fromPrimitives()
        ?string $restoredEventId = null,
        ?string $restoredOccurredAt = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initMetadata(
            correlationId: $restoredCorrelationId,
            restoredEventId: $restoredEventId,
            restoredOccurredAt: $restoredOccurredAt,
        );
    }

    /**
     * Factory: Create from login request context.
     *
     * IP is automatically anonymized for GDPR compliance.
     */
    public static function create(
        ?string $tenantId,
        int $userId,
        string $userEmail,
        string $ipAddress,
        string $userAgent,
        Severity $severity = Severity::INFO
    ): self {
        return new self(
            tenantId: $tenantId !== null ? TenantId::fromString((string) $tenantId) : null,
            userId: $userId,
            userEmail: $userEmail,
            ipAddress: IpAddress::fromString($ipAddress)->anonymize(), // ✅ GDPR
            userAgent: $userAgent,
            severity: $severity,
        );
    }

    /**
     * Get the user ID for listeners.
     */
    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * Get the tenant ID (nullable for system-level login).
     */
    public function tenantId(): ?TenantId
    {
        return $this->tenantId;
    }

    /**
     * Get the anonymized IP address.
     */
    public function ipAddress(): IpAddress
    {
        return $this->ipAddress;
    }

    /**
     * Get the snapshot email.
     */
    public function userEmail(): string
    {
        return $this->userEmail;
    }

    /**
     * Get the user agent.
     */
    public function userAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Get the occurrence timestamp.
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AuditEvent Interface Implementation
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Convert to a flat array for secure logging.
     *
     * SECURITY: IP is ALWAYS anonymized in the output.
     */
    public function toAuditLog(): array
    {
        return [
            'event' => 'new_login_detected',
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId?->toString(),
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'ip_address' => $this->ipAddress->toString(), // Already anonymized
            'user_agent' => $this->userAgent,
            'severity' => $this->severity->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    /**
     * Get the PSR-3 severity level.
     */
    public function severity(): Severity
    {
        return $this->severity;
    }

    /**
     * Get the standardized audit context.
     */
    public function context(): AuditContext
    {
        return AuditContext::fromValues(
            actorId: $this->userId,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            occurredAt: $this->occurredAt
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Safe Serialization (Queue-Safe, Refactor-Proof)
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId?->toString(),
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'ip_address' => $this->ipAddress->toString(),
            'user_agent' => $this->userAgent,
            'severity' => $this->severity->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // Defensive deserialization: user_id is required for login events
        $userId = $primitives['user_id'] ?? null;
        if ($userId === null) {
            throw new \InvalidArgumentException('Missing required key: user_id');
        }

        return new self(
            tenantId: isset($primitives['tenant_id'])
                ? TenantId::fromSerialized($primitives['tenant_id'])
                : null,
            userId: $userId,
            userEmail: $primitives['user_email'] ?? 'unknown@unknown.com',
            ipAddress: IpAddress::fromString($primitives['ip_address'] ?? '0.0.0.0'),
            userAgent: $primitives['user_agent'] ?? 'Unknown',
            severity: Severity::tryFrom($primitives['severity'] ?? '') ?? Severity::INFO,
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
