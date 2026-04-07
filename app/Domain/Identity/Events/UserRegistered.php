<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Domain\Common\Contracts\DomainEventInterface;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use DateTimeImmutable;

/**
 * UserRegistered Event - Scalar Payload Guard
 *
 * adheres to "The Scalar Payload Rule":
 * Carries primitive data only. No Enums allowed in constructor.
 *
 * SECURITY (A6): password_hash removed — credentials must never be
 * serialized in domain events (Event Store / Queue persistence).
 * The hash is set on the aggregate root before the event is recorded.
 *
 * CONTRACT VERSIONING: SCHEMA_VERSION = 2
 * V1 → original with password_hash (purged, CRÍTICO-03)
 * V2 → current: credential-free payload, contract-testing ready
 */
final readonly class UserRegistered implements DomainEventInterface, ShouldDispatchAfterCommit
{
    private const SCHEMA_VERSION = 2;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public string $user_id,
        public string $name, // Added for Read Model Completeness
        public \App\Domain\Shared\ValueObjects\EncryptedEmail $email,
        public string $role,
        public ?string $tenant_id,
        public DateTimeImmutable $occurredOn,
    ) {}

    public static function now(
        string $user_id,
        string $name,
        string $email,
        string $role,
        ?string $tenant_id,
    ): self {
        return new self(
            user_id: $user_id,
            name: $name,
            email: new \App\Domain\Shared\ValueObjects\EncryptedEmail(encrypt($email)),
            role: $role,
            tenant_id: $tenant_id,
            occurredOn: new DateTimeImmutable,
        );
    }

    public static function fromPayload(array $payload): static
    {
        // schema_version is routed by the Upcaster registry before reaching this factory.
        return new self(
            user_id: (string) ($payload['user_id'] ?? ''),
            name: (string) ($payload['name'] ?? 'Unknown User'),
            email: new \App\Domain\Shared\ValueObjects\EncryptedEmail((string) ($payload['email'] ?? '')),
            role: (string) ($payload['role'] ?? 'customer'),
            tenant_id: array_key_exists('tenant_id', $payload)
                ? (is_scalar($payload['tenant_id']) ? (string) $payload['tenant_id'] : null)
                : null,
            occurredOn: isset($payload['occurred_on'])
                ? new DateTimeImmutable($payload['occurred_on'])
                : new DateTimeImmutable,
        );
    }

    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email->value(),
            'role' => $this->role,
            'tenant_id' => $this->tenant_id,
            'occurred_on' => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function aggregateId(): string
    {
        return $this->user_id;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toPayload();
    }
}
