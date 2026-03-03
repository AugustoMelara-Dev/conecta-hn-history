<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Domain\Common\Contracts\DomainEventInterface;
use DateTimeImmutable;

/**
 * UserRegistered Event - Scalar Payload Guard
 *
 * adheres to "The Scalar Payload Rule":
 * Carries primitive data only. No Enums allowed in constructor.
 */
final readonly class UserRegistered implements DomainEventInterface
{
    public function __construct(
        public string $user_id,
        public string $name, // Added for Read Model Completeness
        public string $email,
        public string $password_hash,
        public string $role,
        public ?string $tenant_id,
        public DateTimeImmutable $occurredOn,
    ) {}

    public static function now(
        string $user_id,
        string $name,
        string $email,
        string $password_hash,
        string $role,
        ?string $tenant_id,
    ): self {
        return new self(
            user_id: $user_id,
            name: $name,
            email: $email,
            password_hash: $password_hash,
            role: $role,
            tenant_id: $tenant_id,
            occurredOn: new DateTimeImmutable,
        );
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            user_id: (string) ($payload['user_id'] ?? ''),
            name: (string) ($payload['name'] ?? 'Unknown User'),
            email: (string) ($payload['email'] ?? ''),
            password_hash: (string) ($payload['password_hash'] ?? ''),
            role: (string) ($payload['role'] ?? 'customer'),
            tenant_id: $payload['tenant_id'] ?? null,
            occurredOn: isset($payload['occurred_on'])
                ? new DateTimeImmutable($payload['occurred_on'])
                : new DateTimeImmutable,
        );
    }

    public function toPayload(): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'password_hash' => $this->password_hash,
            'role' => $this->role,
            'tenant_id' => $this->tenant_id,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
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
}
