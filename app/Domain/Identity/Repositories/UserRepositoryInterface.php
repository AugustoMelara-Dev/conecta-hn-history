<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\UserEntity;

/**
 * UserRepositoryInterface - Mutation Capable Contract
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?UserEntity;

    public function findByEmail(string $email): ?UserEntity;

    public function existsByEmail(string $email): bool;

    public function create(array $attributes): UserEntity;

    /**
     * Persist changes to an existing user entity.
     * Required for Bi-Directional Integrity (linking user to tenant).
     */
    public function update(UserEntity $user): void;

    public function save(UserEntity $entity): UserEntity;

    public function updateAvatarIfMissing(int $userId, string $avatarUrl): bool;
}
