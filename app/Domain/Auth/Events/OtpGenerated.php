<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use DateTimeImmutable;

/**
 * OtpGenerated - Zero-Knowledge Audit Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE POPO: No framework dependencies. Zero "use Illuminate\..."        ║
 * ║  ZERO KNOWLEDGE: Does NOT contain the OTP code (secret containment).   ║
 * ║  MINIMALIST PAYLOAD: Only core IDs and timestamp.                       ║
 * ║  IMMUTABLE: final readonly prevents mutation during queue transport.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class OtpGenerated
{
    private const SCHEMA_VERSION = 1;

    private const EVENT_NAME = 'auth.otp_generated';

    public function __construct(
        /** UUID of the OTP record in database */
        public string $otpId,

        /** User ID if known (nullable for registration flows) */
        public ?string $userId,

        /** Tenant context for multi-tenant routing */
        public string $tenantId,

        /** When the OTP was generated */
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Factory method for consistent event creation.
     */
    public static function now(
        string $otpId,
        ?string $userId,
        string $tenantId,
    ): self {
        return new self(
            otpId: $otpId,
            userId: $userId,
            tenantId: $tenantId,
            occurredAt: new DateTimeImmutable,
        );
    }

    /**
     * Canonical event type for serialization/routing.
     */
    public function eventName(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * Schema version for contract validation.
     */
    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Serialize to primitive payload for storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'otp_id' => $this->otpId,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * Hydrate from storage payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self(
            otpId: $payload['otp_id'],
            userId: $payload['user_id'],
            tenantId: $payload['tenant_id'],
            occurredAt: new DateTimeImmutable($payload['occurred_at']),
        );
    }
}
