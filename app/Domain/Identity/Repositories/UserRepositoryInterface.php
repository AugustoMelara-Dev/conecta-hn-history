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

    public function existsByEmail(string $email): bool;

    /**
     * Updates avatar if missing.
     *
     * @param  string  $userId  UUID
     */
    public function updateAvatarIfMissing(string $userId, string $avatarUrl): bool;

    /**
     * Reset a user's password and rotate the remember token.
     *
     * @param  string  $userId  User UUID
     * @param  string  $tenantId  Tenant context for ownership validation
     * @param  string  $passwordHash  Already-hashed password (never plaintext)
     * @param  string  $rememberToken  New random remember token
     *
     * @throws \App\Domain\Identity\Exceptions\UserNotFoundException If user not found within tenant scope
     */
    public function resetPassword(string $userId, string $tenantId, string $passwordHash, string $rememberToken): void;

    // Legacy support removal: delete(int) -> delete(UserEntity)
    public function delete(UserEntity $user): void;
}
