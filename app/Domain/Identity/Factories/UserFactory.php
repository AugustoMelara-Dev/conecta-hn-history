<?php

declare(strict_types=1);

namespace App\Domain\Identity\Factories;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Domain\Shared\ValueObjects\EmailAddress;

final readonly class UserFactory
{
    public function __construct(
        private ClockInterface $clock,
        private UuidGeneratorInterface $uuidGenerator,
    ) {}

    public function createNewUser(
        string $name,
        string $email,
        string $hashedPassword,
        string $role = 'customer'
    ): UserEntity {
        return UserEntity::register(
            uuid: $this->uuidGenerator->generate(),
            name: $name,
            email: $email,
            passwordHash: $hashedPassword,
            role: \App\Domain\Identity\Enums\UserRole::tryFrom($role) ?? \App\Domain\Identity\Enums\UserRole::CUSTOMER,
            tenantId: null
        );
    }
}
