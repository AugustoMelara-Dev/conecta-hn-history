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

        /** Encrypted delivery secret; never plaintext in outbox storage. */
        public ?string $encryptedCode = null,

        public ?string $identifier = null,

        public ?string $recipientName = null,

        public int $expiryMinutes = 10,
    ) {}

    /**
     * Factory method for consistent event creation.
     */
    public static function now(
        string $otpId,
        ?string $userId,
        string $tenantId,
        ?string $encryptedCode = null,
        ?string $identifier = null,
        ?string $recipientName = null,
        int $expiryMinutes = 10,
    ): self {
        return new self(
            otpId: $otpId,
            userId: $userId,
            tenantId: $tenantId,
            occurredAt: new DateTimeImmutable,
            encryptedCode: $encryptedCode,
            identifier: $identifier,
            recipientName: $recipientName,
            expiryMinutes: $expiryMinutes,
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
            'encrypted_code' => $this->encryptedCode,
            'identifier' => $this->identifier,
            'recipient_name' => $this->recipientName,
            'expiry_minutes' => $this->expiryMinutes,
        ];
    }

    /**
     * Hydrate from storage payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            otpId: (string) $payload['otp_id'],
            userId: isset($payload['user_id']) ? (string) $payload['user_id'] : null,
            tenantId: (string) $payload['tenant_id'],
            occurredAt: new DateTimeImmutable((string) $payload['occurred_at']),
            encryptedCode: isset($payload['encrypted_code']) ? (string) $payload['encrypted_code'] : null,
            identifier: isset($payload['identifier']) ? (string) $payload['identifier'] : null,
            recipientName: isset($payload['recipient_name']) ? (string) $payload['recipient_name'] : null,
            expiryMinutes: isset($payload['expiry_minutes']) ? (int) $payload['expiry_minutes'] : 10,
        );
    }
}
