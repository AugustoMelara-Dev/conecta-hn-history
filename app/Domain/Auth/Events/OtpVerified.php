<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use DateTimeImmutable;

final readonly class OtpVerified
{
    private const SCHEMA_VERSION = 1;
    public function schemaVersion(): int { return self::SCHEMA_VERSION; }
    public function __construct(
        public string $eventId,
        public string $otpIdentifier,
        public ?string $userId,
        public string $tenantId,
        public DateTimeImmutable $verifiedAt,
    ) {}

    public static function now(string $otpIdentifier, ?string $userId, string $tenantId): self
    {
        return new self(
            eventId: \Ramsey\Uuid\Uuid::uuid7()->toString(),
            otpIdentifier: $otpIdentifier,
            userId: $userId,
            tenantId: $tenantId,
            verifiedAt: new DateTimeImmutable
        );
    }

    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'event_id' => $this->eventId,
            'otp_identifier' => $this->otpIdentifier,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'verified_at' => $this->verifiedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
