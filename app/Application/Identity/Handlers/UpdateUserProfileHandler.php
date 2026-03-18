<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\UpdateUserProfileCommand;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\User\Events\UserProfileUpdated;

/**
 * UpdateUserProfileHandler - Zero-Trust Profile Management
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMICITY: Explicit transaction management via Contract.                ║
 * ║  IDOR PROTECTION: Validates tenant ownership before ANY mutation.       ║
 * ║  CONSISTENCY: Standardized dependencies (Dispatcher, TransactionMgr).   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserProfileHandler
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TransactionManagerInterface $transactionManager,
        private EventBusInterface $eventBus,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle profile update with mandatory tenant validation and atomic persistence.
     *
     * @throws UserNotFoundException If user not found (Domain Exception)
     * @throws CrossTenantAccessException If tenant mismatch (IDOR attack blocked)
     */
    public function handle(UpdateUserProfileCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: RETRIEVAL (Strict Type Expectation)
        // ═══════════════════════════════════════════════════════════════════
        // FIX: Mandatory 2nd argument $tenantId (Signature Compliance)
        $user = $this->userRepository->findById($command->userId, $command->requestingTenantId);

        if ($user === null) {
            $this->logger->warning('Profile update attempt for non-existent user', $this->sanitizePayload([
                'user_id' => $command->userId,
                'requesting_tenant_id' => $command->requestingTenantId,
            ], $this->scrubber));

            throw UserNotFoundException::withIdAndTenant(
                (string) $command->userId,
                $command->requestingTenantId
            );
        }

        // Integrity Check
        if (! $user instanceof UserEntity) {
            throw new \RuntimeException('Repository returned invalid object type.');
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: IDOR PROTECTION - Tenant Ownership Validation
        // ═══════════════════════════════════════════════════════════════════
        // FIX: Use getter method for private property access
        if ($user->tenantId() !== $command->requestingTenantId) {
            $this->logger->warning('SECURITY: Cross-tenant profile update attempt blocked', $this->sanitizePayload([
                'target_user_id' => $command->userId,
                'target_tenant_id' => $user->tenantId(),
                'requesting_tenant_id' => $command->requestingTenantId,
            ], $this->scrubber));

            throw CrossTenantAccessException::forUserProfile($command->userId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: ATOMIC EXECUTION (Write & Dispatch)
        // ═══════════════════════════════════════════════════════════════════

        $this->transactionManager->transaction(function () use ($user, $command) {

            // Logic: Detect Email Change (Use getter)
            $emailChanged = $user->email() !== $command->email;

            // Mutation: Delegate to Rich Domain Model
            $user->updateProfile(
                name: $command->name,
                email: $command->email
            );

            // Persistence
            $this->userRepository->save($user);

            // Audit
            $this->logger->info('User profile updated successfully', $this->sanitizePayload([
                'user_id' => $command->userId,
                'tenant_id' => $command->requestingTenantId,
                'email_changed' => $emailChanged,
            ], $this->scrubber));

            // Event Dispatch (Inside Transaction)
            $this->eventBus->dispatch(
                UserProfileUpdated::now(
                    userId: $command->userId,
                    tenantId: $command->requestingTenantId,
                    emailChanged: $emailChanged,
                )
            );
        });
    }
}
