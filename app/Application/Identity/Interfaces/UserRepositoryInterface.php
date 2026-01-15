<?php

declare(strict_types=1);

namespace App\Application\Identity\Interfaces;

use App\Domain\Identity\Entities\UserEntity;

/**
 * UserRepositoryInterface - Strict Contract
 */
interface UserRepositoryInterface
{
    public function save(UserEntity $user): void;

    public function findById(string $userId): ?UserEntity;
    
    public function existsByEmail(string $email): bool;
}
