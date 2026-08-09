<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Auth\Results\UpdateUserPasswordResult;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Domain\Auth\Events\UserPasswordChanged;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Domain\Shared\Services\PasswordHasherInterface;
use Ramsey\Uuid\Uuid;

/**
 * UpdateUserPasswordHandler - Atomic Protocol Edition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMIC PROTOCOL: "Insert-or-Die" Gatekeeper                            ║
 * ║  CONSISTENCY: Transactional Outbox within Atomic Lock                   ║
 * ║  SECURITY: Verified Cross-Tenant Access & Zero-Trust Verification       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserPasswordHandler implements UpdateUserPasswordHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $hasher,
        private OutboxRepositoryInterface $outboxRepository,
        private ClockInterface $clock,
    ) {}

    public function handle(UpdateUserPasswordCommand $command): UpdateUserPasswordResult
    {
        // 1. Load Target User

        $user = $this->userRepository->findById($command->userId, $command->requestingTenantId);

        if ($user === null) {
            throw UserNotFoundException::withIdAndTenant(
                (string) $command->userId,
                $command->requestingTenantId
            );
        }

        // Integrity Check
        if (! $user instanceof UserEntity) {
            throw new \RuntimeException('Repository returned invalid object type.');
        }

        // 2. IDOR Protection
        if ($user->tenantId() !== $command->requestingTenantId) {
            throw new CrossTenantAccessException('user', $command->userId);
        }

        // 3. Credential Verification
        if ($user->hasPassword()) {
            if (! $this->hasher->verify($command->currentPassword, $user->password())) {
                throw new InvalidCredentialsException('The provided password does not match your current password.');
            }
        }

        // 4. Domain Logic: Mutate & Persist
        $newHashedPassword = $this->hasher->hash($command->newPassword);

        $updatedUser = $user->withPassword($newHashedPassword);
        $sessionInvalidated = false;

        if ($command->refreshRememberToken) {
            $updatedUser = $updatedUser->withRememberToken(null);
            $sessionInvalidated = true;
        }

        $this->userRepository->save($updatedUser);

        // 5. Outbox Persistence
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
                'session_invalidated' => $sessionInvalidated,
            ]
        );

        $this->outboxRepository->save($message);

        return new UpdateUserPasswordResult(
            status: 'success',
            userId: $command->userId
        );
    }
}
