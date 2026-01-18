<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * DeleteUserHandler - Secure User Deletion Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRASH FIXED: Corrected findById signature (requires tenantId).          ║
 * ║  CONSISTENCY: Transactional Outbox & Idempotency Barrier.               ║
 * ║  ATOMICITY: Explicit transaction management via Contract.                ║
 * ║  DEPENDENCY INVERSION: Uses standard Interfaces Only.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class DeleteUserHandler
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TransactionManagerInterface $transactionManager,
        private OutboxRepositoryInterface $outboxRepository,
        private IdempotencyServiceInterface $idempotencyService,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * Handle user deletion command.
     *
     * @param DeleteUserCommand $command
     * @return void
     * @throws UserNotFoundException If user not found (Domain Exception)
     * @throws CrossTenantAccessException If tenant mismatch (IDOR attack)
     */
    public function handle(DeleteUserCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: IDEMPOTENCY BARRIER
        // ═══════════════════════════════════════════════════════════════════
        if ($this->idempotencyService->has($command->idempotencyKey)) {
            $this->logger->info('Idempotency hit: Returning cached result (void)', ['key' => $command->idempotencyKey]);
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: READ - Load Target User
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->userRepository->findById($command->userId, $command->requestingTenantId);

        if ($user === null) {
            throw UserNotFoundException::withIdAndTenant(
                (string) $command->userId,
                $command->requestingTenantId
            );
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: GUARD - IDOR PROTECTION
        // ═══════════════════════════════════════════════════════════════════
        if ($user->tenantId !== $command->requestingTenantId) {
            $this->logger->warning('SECURITY: Cross-tenant user deletion attempt blocked', $this->sanitizePayload([
                'target_user_id' => $command->userId,
                'target_tenant_id' => $user->tenantId,
                'requesting_tenant_id' => $command->requestingTenantId,
            ]));

            throw CrossTenantAccessException::forUserDeletion($command->userId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4: WRITE - ATOMIC DELETION AND OUTBOX
        // ═══════════════════════════════════════════════════════════════════
        
        $this->transactionManager->transaction(function () use ($user, $command) {
            $userId = $user->id;
            $tenantId = $user->tenantId;
            $email = $user->email;
            $name = $user->name;

            // 1. Delete (Persistence)
            $this->userRepository->delete($user);

            // 2. Outbox Persistence
            $event = UserDeleted::fromSnapshot(
                userId: $userId,
                tenantId: $tenantId,
                email: $email,
                name: $name,
            );

            $message = new DomainEventMessage(
                eventId: Uuid::uuid7()->toString(),
                type: UserDeleted::class,
                aggregateId: (string) $userId,
                payload: json_encode($event->toPayload()), // Assuming toPayload() exists or similar serialization
                occurredAt: $this->clock->now(),
                metadata: [
                    'tenant_id' => $tenantId,
                    'causation_id' => $command->idempotencyKey,
                    'requesting_tenant_id' => $command->requestingTenantId,
                ]
            );

            $this->outboxRepository->save($message);
            
            $this->logger->info('User deleted and persisted to Outbox', $this->sanitizePayload([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]));
        });

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 5: IDEMPOTENCY RECORDING
        // ═══════════════════════════════════════════════════════════════════
        $this->idempotencyService->store($command->idempotencyKey, ['status' => 'deleted']);
    }
}
