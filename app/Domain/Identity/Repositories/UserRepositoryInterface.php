<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\UserEntity;

/**
 * UserRepositoryInterface - Port for User Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CLEAN ARCHITECTURE: Domain depends on NOTHING from Infrastructure.     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Returns UserEntity (pure Domain object), NOT Eloquent Model          ║
 * ║  - Infrastructure provides EloquentUserRepository implementation         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface UserRepositoryInterface
{
    /**
     * Find a user by primary identifier.
     */
    public function findById(int $id): ?UserEntity;

    /**
     * Find a user by email address.
     */
    public function findByEmail(string $email): ?UserEntity;

    /**
     * Create a new user entity.
     *
     * @param array<string, mixed> $attributes
     * @return UserEntity
     */
    public function create(array $attributes): UserEntity;

    /**
     * Persist changes to a user entity.
     * 
     * @param UserEntity $entity The entity to save
     * @return UserEntity Fresh entity reflecting persisted state
     */
    public function save(UserEntity $entity): UserEntity;

    /**
     * Atomic update of avatar only if currently missing.
     * Prevents race conditions (blind overwrite) in concurrent logins.
     * 
     * @param int $userId
     * @param string $avatarUrl
     * @return bool True if updated, False if already present or phantom
     */
    public function updateAvatarIfMissing(int $userId, string $avatarUrl): bool;
}
