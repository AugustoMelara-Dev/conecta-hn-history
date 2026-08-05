<?php

declare(strict_types=1);

namespace App\Events\Security;

use App\Contracts\Events\AuditEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Enums\Severity;
use App\ValueObjects\AuditContext;

/**
 * FailedLoginAttempt - Audit Event for Brute Force Detection
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY AUDIT: Authentication Failure Tracking                        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS REPRESENTED:                                                ║
 * ║    - An authentication attempt occurred with invalid credentials        ║
 * ║    - The attempt originated from a specific IP (anonymized for GDPR)    ║
 * ║    - The attempt targeted a specific email address                     ║
 * ║                                                                          ║
 * ║  ORM-AGNOSTIC DESIGN:                                                   ║
 * ║    - ❌ NO Eloquent models                                              ║
 * ║    - ❌ NO ShouldBroadcast (security risk)                              ║
 * ║    - ✅ Synchronous execution (logged immediately)                     ║
 * ║    - ✅ IP anonymized via AuditContext                                  ║
 * ║                                                                          ║
 * ║  LISTENERS CAN:                                                         ║
 * ║    - Increment rate limiter counter                                     ║
 * ║    - Trigger CAPTCHA after N attempts                                   ║
 * ║    - Block IP after threshold exceeded                                  ║
 * ║    - Send alert to security team                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * DISPATCH POINT: AuthenticatedSessionController::store() on validation failure
 */
final readonly class FailedLoginAttempt implements AuditEvent
{
    /**
     * @param  string  $email  The email address used in the failed attempt
     * @param  TenantId|null  $tenantId  The tenant context (null if central login)
     * @param  AuditContext  $context  Standardized audit metadata (IP anonymized)
     * @param  string  $failureReason  Machine-readable failure code
     */
    public function __construct(
        public string $email,
        public ?TenantId $tenantId,
        private AuditContext $context,
        public string $failureReason = 'invalid_credentials',
    ) {}

    /**
     * Factory: Create from the current request context.
     */
    public static function create(
        string $email,
        ?string $tenantId = null,
        string $failureReason = 'invalid_credentials'
    ): self {
        return new self(
            email: $email,
            tenantId: $tenantId !== null ? TenantId::fromString((string) $tenantId) : null,
            context: AuditContext::capture(actorId: null, anonymizeIp: true),
            failureReason: $failureReason,
        );
    }

    /**
     * Get the attempt fingerprint for rate limiting.
     */
    public function fingerprint(): string
    {
        return md5($this->email.'|'.$this->context->ipAddress->toString());
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
        $safeContext = $this->context->withAnonymizedIp();

        return [
            'event' => 'failed_login_attempt',
            'email' => $this->email,
            'tenant_id' => $this->tenantId?->toString(),
            'failure_reason' => $this->failureReason,
            'fingerprint' => $this->fingerprint(),
            ...$safeContext->toArray(anonymize: true),
        ];
    }

    /**
     * Get the PSR-3 severity level.
     */
    public function severity(): Severity
    {
        return Severity::INFO;
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
            'email' => $this->email,
            'tenant_id' => $this->tenantId?->toString(),
            'failure_reason' => $this->failureReason,
            'context' => $this->context->toArray(anonymize: false),
        ];
    }

    public static function fromPrimitives(array $data): self
    {
        // Defensive deserialization: email is required
        $email = $data['email'] ?? null;
        if ($email === null) {
            throw new \InvalidArgumentException('Missing required key: email');
        }

        return new self(
            email: $email,
            tenantId: isset($data['tenant_id']) ? TenantId::fromSerialized($data['tenant_id']) : null,
            context: AuditContext::fromPrimitives($data['context'] ?? []),
            failureReason: $data['failure_reason'] ?? 'invalid_credentials',
        );
    }
}
