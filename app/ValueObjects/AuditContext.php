<?php

declare(strict_types=1);

namespace App\ValueObjects;

use DateTimeImmutable;

/**
 * AuditContext - Composite Value Object for Security Event Metadata
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUDIT STANDARDIZATION: Consistent Security Event Payload               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM: Security events have inconsistent metadata formats            ║
 * ║    - FailedLoginAttempt: ip_address, user_agent                        ║
 * ║    - SensitiveDataAccessed: ip, agent, actor_id                        ║
 * ║    - TenantSuspended: suspended_by_id, timestamp                       ║
 * ║                                                                          ║
 * ║  SOLUTION: Standardized AuditContext for all security events           ║
 * ║    - actorId: Who performed the action (null for system/anonymous)     ║
 * ║    - ipAddress: Where the request originated (GDPR-anonymizable)       ║
 * ║    - userAgent: Browser/client fingerprint                             ║
 * ║    - occurredAt: Immutable timestamp of the event                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   $context = AuditContext::capture($request, auth()->id());
 *   $event = new FailedLoginAttempt($email, $tenantId, $context);
 */
final readonly class AuditContext
{
    /**
     * @param  int|string|null  $actorId  The user ID who triggered the event (null for anonymous/system)
     * @param  IpAddress  $ipAddress  The origin IP address (should be anonymized for logging)
     * @param  string  $userAgent  The HTTP User-Agent header (browser fingerprint)
     * @param  DateTimeImmutable  $occurredAt  The exact moment the event occurred
     */
    public function __construct(
        public int|string|null $actorId,
        public IpAddress $ipAddress,
        public string $userAgent,
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Factory: Capture context from the current HTTP request.
     *
     * @param  int|string|null  $actorId  The authenticated user ID (null if guest)
     * @param  bool  $anonymizeIp  Whether to anonymize the IP for GDPR compliance
     */
    public static function capture(int|string|null $actorId = null, bool $anonymizeIp = false): self
    {
        $ip = new IpAddress(request()->ip() ?? '0.0.0.0');

        return new self(
            actorId: $actorId,
            ipAddress: $anonymizeIp ? $ip->anonymize() : $ip,
            userAgent: request()->userAgent() ?? 'Unknown',
            occurredAt: new DateTimeImmutable,
        );
    }

    /**
     * Factory: Create context for system-initiated actions (no HTTP request).
     */
    public static function system(): self
    {
        return new self(
            actorId: null,
            ipAddress: new IpAddress('127.0.0.1'),
            userAgent: 'System/ConectaHN',
            occurredAt: new DateTimeImmutable,
        );
    }

    /**
     * Get a version with anonymized IP (for GDPR-compliant logging).
     */
    public function withAnonymizedIp(): self
    {
        return new self(
            actorId: $this->actorId,
            ipAddress: $this->ipAddress->anonymize(),
            userAgent: $this->userAgent,
            occurredAt: $this->occurredAt,
        );
    }

    /**
     * Convert to array for JSON serialization or logging.
     *
     * @param  bool  $anonymize  Whether to anonymize the IP in output
     */
    public function toArray(bool $anonymize = true): array
    {
        $ip = $anonymize ? $this->ipAddress->anonymize() : $this->ipAddress;

        return [
            'actor_id' => $this->actorId,
            'ip_address' => $ip->toString(),
            'ip_anonymized' => $ip->isAnonymized,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * Reconstruct AuditContext from primitive array.
     */
    public static function fromPrimitives(array $data): self
    {
        return new self(
            actorId: $data['actor_id'] ?? null,
            ipAddress: IpAddress::fromString($data['ip_address'] ?? '0.0.0.0'),
            userAgent: $data['user_agent'] ?? 'Unknown',
            occurredAt: new DateTimeImmutable($data['occurred_at'] ?? 'now'),
        );
    }

    /**
     * Factory: Create from explicit values (for testing or event reconstruction).
     */
    public static function fromValues(
        int|string|null $actorId,
        IpAddress $ipAddress,
        string $userAgent,
        DateTimeImmutable $occurredAt
    ): self {
        return new self($actorId, $ipAddress, $userAgent, $occurredAt);
    }

    /**
     * Get a fingerprint for rate limiting (combines IP + User Agent).
     */
    public function getFingerprint(): string
    {
        return md5($this->ipAddress->toString().'|'.$this->userAgent);
    }

    /**
     * Check if this context is from an authenticated user.
     */
    public function isAuthenticated(): bool
    {
        return $this->actorId !== null;
    }

    /**
     * Get the timestamp as ISO 8601 string.
     */
    public function getTimestamp(): string
    {
        return $this->occurredAt->format(DateTimeImmutable::ATOM);
    }
}
