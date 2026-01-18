<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Auth\Events\UserPasswordChanged;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Services\PasswordHasherInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * UpdateUserPasswordHandler - Zero-Trust Password Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRASH FIXED: Corrected findById signature (requires tenantId).          ║
 * ║  CONSISTENCY: Transactional Outbox & Idempotency Barrier.               ║
 * ║  IDOR PROTECTION: Validates tenant ownership before ANY mutation.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserPasswordHandler
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $hasher,
        private TransactionManagerInterface $transactionManager,
        private OutboxRepositoryInterface $outboxRepository,
        private IdempotencyServiceInterface $idempotencyService,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function handle(UpdateUserPasswordCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: IDEMPOTENCY BARRIER
        // ═══════════════════════════════════════════════════════════════════
        if ($this->idempotencyService->has($command->idempotencyKey)) {
            $this->logger->info('Idempotency hit: Returning cached result (void)', ['key' => $command->idempotencyKey]);
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: RETRIEVAL (Strict Type Expectation)
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->userRepository->findById($command->userId, $command->requestingTenantId);

        if ($user === null) {
            $this->logger->warning('Password update attempt for non-existent user', $this->sanitizePayload([
                'user_id' => $command->userId,
                'requesting_tenant_id' => $command->requestingTenantId,
            ]));
            
            throw UserNotFoundException::withIdAndTenant(
                (string) $command->userId,
                $command->requestingTenantId
            );
        }

        // Integrity Check: Ensure we received a Domain Entity
        if (!$user instanceof UserEntity) {
            throw new \RuntimeException('Repository returned invalid object type.');
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: IDOR PROTECTION
        // ═══════════════════════════════════════════════════════════════════
        if ($user->tenantId !== $command->requestingTenantId) {
            $this->logger->warning('SECURITY: Cross-tenant password update attempt blocked', $this->sanitizePayload([
                'target_user_id' => $command->userId,
                'target_tenant_id' => $user->tenantId,
                'requesting_tenant_id' => $command->requestingTenantId,
            ]));

            throw new CrossTenantAccessException('user', $command->userId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4: CREDENTIAL VERIFICATION
        // ═══════════════════════════════════════════════════════════════════
        if ($user->hasPassword()) {
            if (!$this->hasher->verify($command->currentPassword, $user->password)) {
                $this->logger->info('Password verification failed', $this->sanitizePayload([
                    'user_id' => $command->userId,
                    'tenant_id' => $command->requestingTenantId,
                ]));
                throw new InvalidCredentialsException('The provided password does not match your current password.');
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 5: HASHING
        // ═══════════════════════════════════════════════════════════════════
        $newHashedPassword = $this->hasher->hash($command->newPassword);

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 6: MUTATION & PERSISTENCE (Atomic Wrapper)
        // ═══════════════════════════════════════════════════════════════════
        
        $this->transactionManager->transaction(function () use ($user, $newHashedPassword, $command) {
            
            $updatedUser = $user->withPassword($newHashedPassword);

            $sessionInvalidated = false;
            if ($command->refreshRememberToken) {
                $updatedUser = $updatedUser->withRememberToken(null);
                $sessionInvalidated = true;
            }

            $this->userRepository->save($updatedUser);
            
            // Outbox Persistence
            $event = UserPasswordChanged::now(
                userId: (string) $command->userId,
                tenantId: $command->requestingTenantId,
                sessionInvalidated: $sessionInvalidated,
            );

            $message = new DomainEventMessage(
                eventId: Uuid::uuid7()->toString(),
                type: UserPasswordChanged::class,
                aggregateId: (string) $command->userId,
                payload: json_encode($event->toPayload()),
                occurredAt: $this->clock->now(),
                metadata: [
                    'tenant_id' => $command->requestingTenantId,
                    'causation_id' => $command->idempotencyKey,
                    'session_invalidated' => $sessionInvalidated
                ]
            );

            $this->outboxRepository->save($message);

            $this->logger->info('Password updated and persisted to Outbox', $this->sanitizePayload([
                'user_id' => $command->userId,
                'tenant_id' => $command->requestingTenantId,
                'session_invalidated' => $sessionInvalidated,
            ]));
        });

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 7: IDEMPOTENCY RECORDING
        // ═══════════════════════════════════════════════════════════════════
        $this->idempotencyService->store($command->idempotencyKey, ['status' => 'updated']);
    }
}
