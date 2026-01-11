<?php

declare(strict_types=1);

namespace App\Domain\Identity\Factories;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\ValueObjects\EmailAddress;
use DateTimeImmutable;

final readonly class UserFactory
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function createNewUser(
        string $name, 
        string $email, 
        string $hashedPassword, 
        string $role = 'customer'
    ): UserEntity {
        return new UserEntity(
            id: 0, // Transient ID
            tenantId: 0, // Default/Global
            email: new EmailAddress($email),
            name: $name,
            role: $role,
            createdAt: $this->clock->now(), // Deterministic Time
            updatedAt: $this->clock->now(),
            password: $hashedPassword,
        );
    }
}
