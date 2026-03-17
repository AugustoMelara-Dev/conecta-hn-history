<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\ResetUserPasswordCommand;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Identity\Events\UserPasswordResetRequested;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Application\Shared\Contracts\IdGeneratorInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Domain\Security\Contracts\HasherInterface;
use Psr\Log\LoggerInterface;

/**
 * ResetUserPasswordHandler - Application Layer Password Reset Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RESPONSIBILITY: Hash → Delegate to Repository → Dispatch Event.      ║
 * ║  FRAMEWORK AGNOSTIC: Uses HasherInterface/EventDispatcherInterface.   ║
 * ║  TENANT AWARE: Validates tenant ownership via repository scope.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Eliminated Illuminate\Contracts coupling.
 * Now uses Domain contracts (HasherInterface, EventDispatcherInterface).
 */
final readonly class ResetUserPasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private HasherInterface $hasher,
        private OutboxRepositoryInterface $outboxRepository,
        private ClockInterface $clock,
        private IdGeneratorInterface $idGenerator,
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
        $rememberToken = bin2hex(random_bytes(30));

        $this->userRepository->resetPassword(
            $command->userId,
            $command->tenantId,
            $passwordHash,
            $rememberToken,
        );

        // Dispatch domain event. Infrastructure listeners will handle framework hooks
        // (e.g., session invalidation, auth guard refresh).
        $user = $this->userRepository->findById($command->userId, $command->tenantId);
        if ($user !== null) {
            $event = new UserPasswordResetRequested(
                $command->userId,
                $command->tenantId,
                $this->clock->now()
            );
            $message = new DomainEventMessage(
                eventId: $this->idGenerator->generate(),
                type: get_class($event),
                aggregateId: $command->userId,
                payload: json_encode(['userId' => $event->userId, 'tenantId' => $event->tenantId]),
                occurredAt: $this->clock->now(),
                metadata: ['tenant_id' => $command->tenantId]
            );
            $this->outboxRepository->append($message);
        }

        $this->logger->info('Password reset completed', [
            'user_id' => $command->userId,
            'tenant_id' => $command->tenantId,
        ]);
    }
}
