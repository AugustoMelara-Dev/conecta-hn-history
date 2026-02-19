<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Identity\Commands\ReactivateUserCommand;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * ReactivateUserHandler - Application Layer Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RESPONSIBILITY: Orchestrate domain logic + infrastructure concerns.    ║
 * ║  PATTERN: Find Entity → Validate Tenant → Reactivate → Persist → Log. ║
 * ║  ATOMICITY: Wrapped in TransactionManagerInterface for consistency.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Extracted from ReactivateUserAction (Infrastructure Adapter) to enforce
 * Ports & Adapters separation. Business logic belongs HERE, not in adapters.
 */
final readonly class ReactivateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TransactionManagerInterface $transactionManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Handle user reactivation with tenant validation and atomic persistence.
     *
     * @throws \DomainException If user is already active.
     * @throws \InvalidArgumentException If user not found or tenant mismatch.
     */
    public function handle(ReactivateUserCommand $command): void
    {
        $user = $this->userRepository->findById($command->userId, $command->tenantId);

        if ($user === null) {
            throw new \InvalidArgumentException(
                "User not found: {$command->userId} in tenant {$command->tenantId}"
            );
        }

        $this->transactionManager->transaction(function () use ($user, $command) {
            $user->reactivate();

            $this->userRepository->save($user);

            $this->logger->info('User successfully reactivated', [
                'user_id' => $command->userId,
                'tenant_id' => $command->tenantId,
            ]);
        });
    }
}
