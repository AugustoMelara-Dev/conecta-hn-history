<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use DateTimeImmutable;

final readonly class OtpVerified
{
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
            'event_id' => $this->eventId,
            'otp_identifier' => $this->otpIdentifier,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'verified_at' => $this->verifiedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
