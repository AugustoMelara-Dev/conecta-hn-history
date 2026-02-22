<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\ResetUserPasswordCommand;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * ResetUserPasswordHandler - Application Layer Password Reset Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RESPONSIBILITY: Hash → Delegate to Repository → Dispatch Event.      ║
 * ║  FRAMEWORK AGNOSTIC: Uses Hasher/Dispatcher/Repository contracts.     ║
 * ║  TENANT AWARE: Validates tenant ownership via repository scope.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Eliminated direct App\Models\User dependency.
 * Now delegates persistence to UserRepositoryInterface::resetPassword().
 */
final readonly class ResetUserPasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private Hasher $hasher,
        private Dispatcher $dispatcher,
        private LoggerInterface $logger,
    ) {}

    /**
     * Handle password reset command.
     *
     * @throws \App\Domain\Identity\Exceptions\UserNotFoundException If user not found within tenant scope.
     */
    public function handle(ResetUserPasswordCommand $command): void
    {
        $passwordHash = $this->hasher->make($command->password);
        $rememberToken = Str::random(60);

        $this->userRepository->resetPassword(
            $command->userId,
            $command->tenantId,
            $passwordHash,
            $rememberToken,
        );

        // Dispatch Laravel's native PasswordReset event for framework hooks
        // (e.g., session invalidation, auth guard refresh).
        // Note: This uses the user object from findById for event compatibility.
        $user = $this->userRepository->findById($command->userId, $command->tenantId);
        if ($user !== null) {
            $this->dispatcher->dispatch(new PasswordReset($user));
        }

        $this->logger->info('Password reset completed', [
            'user_id' => $command->userId,
            'tenant_id' => $command->tenantId,
        ]);
    }
}
