<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Services\PasswordHasherInterface;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;

/**
 * UpdateUserPasswordHandler - Secure Password Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TYPE SAFETY: Operates strictly on UserEntity, never Eloquent Models.   ║
 * ║  ZERO MAGIC: No Facades. Pure Dependency Injection.                     ║
 * ║  DEFENSIVE: Validates existence and credentials before mutation.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserPasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $hasher,
    ) {}

    public function handle(UpdateUserPasswordCommand $command): void
    {
        // STEP 1: RETRIEVAL (Strict Type Expectation)
        // Note: Repository must be implemented to return UserEntity or null
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new UserNotFoundException("User identified by ID {$command->userId} not found.");
        }

        // Integrity Check: Ensure we received a Domain Entity
        if (! $user instanceof UserEntity) {
             // In a perfect world, PHP return types handle this. 
             // But if Repo interface is loose (mixed/object), this guards runtime.
             throw new \RuntimeException("Repository returned invalid object type.");
        }

        // STEP 2: VERIFICATION (Context-Aware Guard Clause)
        // If user has a password, verify it. If not (Social Login), skip check to allow setting it.
        if ($user->hasPassword()) {
            if (! $this->hasher->verify($command->currentPassword, $user->password)) {
                throw new InvalidCredentialsException('The provided password does not match your current password.');
            }
        }

        // STEP 3: HASHING (Infrastructure concern delegated to Service)
        $newHashedPassword = $this->hasher->hash($command->newPassword);

        // STEP 4: MUTATION (Immutable Wither Pattern)
        // changepassword semantic alias for withPassword could be added to Entity, using withPassword for now
        $updatedUser = $user->withPassword($newHashedPassword);

        // STEP 5: PERSISTENCE (Transactional save)
        $this->userRepository->save($updatedUser);
    }
}
