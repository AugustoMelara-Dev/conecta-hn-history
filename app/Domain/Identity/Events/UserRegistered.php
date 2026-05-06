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
final class UserRegistered implements DomainEventInterface
{
    public readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $user_id,
        public readonly string $name, // Added for Read Model Completeness
        public readonly string $email,
        public readonly string $password_hash,
        public readonly string $role,
        public readonly string $tenant_id,
        ?DateTimeImmutable $occurredOn = null
    ) {
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable;
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            user_id: (string) ($payload['user_id'] ?? ''),
            name: (string) ($payload['name'] ?? 'Unknown User'),
            email: (string) ($payload['email'] ?? ''),
            password_hash: (string) ($payload['password_hash'] ?? ''),
            role: (string) ($payload['role'] ?? 'customer'),
            tenant_id: (string) ($payload['tenant_id'] ?? ''),
            occurredOn: isset($payload['occurred_on'])
                ? new DateTimeImmutable($payload['occurred_on'])
                : new DateTimeImmutable
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
