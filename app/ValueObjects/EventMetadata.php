<?php

declare(strict_types=1);

namespace App\ValueObjects;

use DateTimeImmutable;

/**
 * EventMetadata - Immutable Context Carrier for Domain Events
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEEP IMMUTABILITY: No Mutable Fields Allowed                           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WHY THIS EXISTS:                                                       ║
 * ║                                                                          ║
 * ║  ANTI-PATTERN (Silent Mutation):                                        ║
 * ║    final readonly class Event {                                         ║
 * ║        public function __construct(                                     ║
 * ║            public array $metadata, // ← MUTABLE!                        ║
 * ║        ) {}                                                             ║
 * ║    }                                                                    ║
 * ║    $event->metadata['key'] = 'new_value'; // MUTATION!                  ║
 * ║                                                                          ║
 * ║  SOLUTION:                                                              ║
 * ║    Use this immutable VO for all contextual metadata.                   ║
 * ║    All "modifications" return new instances (copy-on-write).            ║
 * ║                                                                          ║
 * ║  CONTENTS:                                                              ║
 * ║    - correlationId: Links related events across services                ║
 * ║    - causationId: ID of the event that caused this event                ║
 * ║    - actorId: User who triggered the action (null for system)           ║
 * ║    - ipAddress: Anonymized IP of the actor                              ║
 * ║    - userAgent: Browser/client identifier                               ║
 * ║    - occurredAt: Immutable timestamp                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class EventMetadata
{
    /**
     * @param string $correlationId Links related events in a saga/workflow
     * @param string|null $causationId ID of the event that caused this event
     * @param int|null $actorId User who triggered the action (null for system)
     * @param IpAddress|null $ipAddress Anonymized IP of the actor
     * @param string|null $userAgent Browser/client identifier
     * @param DateTimeImmutable $occurredAt Immutable timestamp
     */
    public function __construct(
        public string $correlationId,
        public ?string $causationId,
        public ?int $actorId,
        public ?IpAddress $ipAddress,
        public ?string $userAgent,
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Factory: Capture from current request context.
     */
    public static function capture(?int $actorId = null): self
    {
        return new self(
            correlationId: self::generateCorrelationId(),
            causationId: null,
            actorId: $actorId ?? auth()->id(),
            ipAddress: self::captureIp(),
            userAgent: request()->userAgent(),
            occurredAt: new DateTimeImmutable(),
        );
    }

    /**
     * Factory: Create for system-initiated events.
     */
    public static function system(): self
    {
        return new self(
            correlationId: self::generateCorrelationId(),
            causationId: null,
            actorId: null,
            ipAddress: null,
            userAgent: 'system',
            occurredAt: new DateTimeImmutable(),
        );
    }

    /**
     * Factory: Create from a parent event (causation chain).
     */
    public static function fromParent(self $parent): self
    {
        return new self(
            correlationId: $parent->correlationId, // Same correlation
            causationId: $parent->correlationId,   // Parent is the cause
            actorId: $parent->actorId,
            ipAddress: $parent->ipAddress,
            userAgent: $parent->userAgent,
            occurredAt: new DateTimeImmutable(),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Copy-on-Write Methods (Immutable Modifications)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create new instance with different causation ID.
     */
    public function withCausationId(string $causationId): self
    {
        return new self(
            correlationId: $this->correlationId,
            causationId: $causationId,
            actorId: $this->actorId,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            occurredAt: $this->occurredAt,
        );
    }

    /**
     * Create new instance with anonymized IP.
     */
    public function withAnonymizedIp(): self
    {
        return new self(
            correlationId: $this->correlationId,
            causationId: $this->causationId,
            actorId: $this->actorId,
            ipAddress: $this->ipAddress?->anonymize(),
            userAgent: $this->userAgent,
            occurredAt: $this->occurredAt,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Serialization
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Convert to array for logging/serialization.
     */
    public function toArray(bool $anonymize = true): array
    {
        $ip = $anonymize && $this->ipAddress !== null
            ? $this->ipAddress->anonymize()->toString()
            : $this->ipAddress?->toString();

        return [
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'actor_id' => $this->actorId,
            'ip_address' => $ip,
            'user_agent' => $this->userAgent,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Private Helpers
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Generate a unique correlation ID (UUID v4).
     */
    private static function generateCorrelationId(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }

    /**
     * Capture current request IP as IpAddress VO.
     */
    private static function captureIp(): ?IpAddress
    {
        $ip = request()->ip();
        
        if ($ip === null) {
            return null;
        }

        try {
            return IpAddress::fromString($ip);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
