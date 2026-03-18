<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Auth\Results\DeleteUserResult;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Application\Shared\Contracts\IdGeneratorInterface;

/**
 * DeleteUserHandler - Pure Core Edition (Russian Doll Pattern)
 */
final readonly class DeleteUserHandler implements DeleteUserHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private OutboxRepositoryInterface $outboxRepository,
        private ClockInterface $clock,
        private IdGeneratorInterface $idGenerator,
    ) {}

    public function handle(DeleteUserCommand $command): DeleteUserResult
    {
        // 1. Load Target User
        $user = $this->userRepository->findById($command->userId, $command->requestingTenantId);

        if ($user === null) {
            throw UserNotFoundException::withIdAndTenant(
                (string) $command->userId,
                $command->requestingTenantId
            );
        }

        // 2. IDOR Protection (Redundant but Safe)
        if ($user->tenantId() !== $command->requestingTenantId) {
            throw CrossTenantAccessException::forUserDeletion($command->userId);
        }

        // 3. Domain Logic: Delete
        $userId = $user->id();
        $tenantId = $user->tenantId();

        $this->userRepository->delete($user);

        // 4. Outbox Persistence
        $event = UserDeleted::fromSnapshot(
            userId: $userId,
            tenantId: $tenantId,
        );

        $this->outboxRepository->append($event);

        return new DeleteUserResult('deleted', (string) $userId);
    }
}
