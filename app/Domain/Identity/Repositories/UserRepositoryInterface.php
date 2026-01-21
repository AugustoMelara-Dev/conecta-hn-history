<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\UserEntity;

/**
 * UserRepositoryInterface - Strict Type Contracts
 *
 * Enforces String UUIDs and Entity Encapsulation.
 * The "Int ID" era is over.
 */
interface UserRepositoryInterface
{
    public function save(UserEntity $user): void;

    public function findById(string $id, string $tenantId): ?UserEntity;

    public function findByEmail(string $email, string $tenantId): ?UserEntity;

    /**
     * Updates avatar if missing.
     *
     * @param  string  $userId  UUID
     */
    public function updateAvatarIfMissing(string $userId, string $avatarUrl): bool;

    // Legacy support removal: delete(int) -> delete(UserEntity)
    public function delete(UserEntity $user): void;
}
