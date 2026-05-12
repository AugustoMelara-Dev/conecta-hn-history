<?php

declare(strict_types=1);

namespace App\Domain\Identity\Factories;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Shared\Contracts\ClockInterface;
use Illuminate\Support\Str;

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
        $resolvedRole = UserRole::tryFrom($role) ?? UserRole::CUSTOMER;

        return UserEntity::register(
            uuid: (string) Str::orderedUuid(),
            name: $name,
            email: $email,
            passwordHash: $hashedPassword,
            role: $resolvedRole,
            // Tenant is created in the same workflow; keep an explicit placeholder.
            tenantId: (string) Str::orderedUuid(),
        );
    }
}
