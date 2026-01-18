<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Events\UserPasswordChanged;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Domain\Shared\Services\PasswordHasherInterface;
use App\Application\Shared\Contracts\EventBusInterface;
use Illuminate\Support\Facades\Log;

/**
 * UpdateUserPasswordHandler - Zero-Trust Password Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRASH FIXED: Corrected findById signature (requires tenantId).          ║
 * ║  ATOMICITY: Wrapped in TransactionManager for data consistency.          ║
 * ║  IDOR PROTECTION: Validates tenant ownership before ANY mutation.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserPasswordHandler
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $hasher,
        private EventBusInterface $eventBus,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(UpdateUserPasswordCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: RETRIEVAL (Strict Type Expectation)
        // ═══════════════════════════════════════════════════════════════════
        // FIX #1: Mandatory 2nd argument $tenantId (Signature Compliance)
        $user = $this->userRepository->findById($command->userId, $command->requestingTenantId);

        if ($user === null) {
            Log::warning('Password update attempt for non-existent user', $this->sanitizePayload([
                'user_id' => $command->userId,
                'requesting_tenant_id' => $command->requestingTenantId,
            ]));
            
            // Updated to use Domain Factory
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
        // PHASE 2: IDOR PROTECTION - Tenant Ownership Validation
        // ═══════════════════════════════════════════════════════════════════
        if ($user->tenantId !== $command->requestingTenantId) {
            Log::warning('SECURITY: Cross-tenant password update attempt blocked', $this->sanitizePayload([
                'target_user_id' => $command->userId,
                'target_tenant_id' => $user->tenantId,
                'requesting_tenant_id' => $command->requestingTenantId,
            ]));

            throw new CrossTenantAccessException('user', $command->userId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: CREDENTIAL VERIFICATION
        // ═══════════════════════════════════════════════════════════════════
        if ($user->hasPassword()) {
            if (!$this->hasher->verify($command->currentPassword, $user->password)) {
                Log::info('Password verification failed', $this->sanitizePayload([
                    'user_id' => $command->userId,
                    'tenant_id' => $command->requestingTenantId,
                ]));
                throw new InvalidCredentialsException('The provided password does not match your current password.');
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4: HASHING
        // ═══════════════════════════════════════════════════════════════════
        $newHashedPassword = $this->hasher->hash($command->newPassword);

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 5: MUTATION & PERSISTENCE (Atomic Wrapper)
        // ═══════════════════════════════════════════════════════════════════
        
        $this->transactionManager->transaction(function () use ($user, $newHashedPassword, $command) {
            
            $updatedUser = $user->withPassword($newHashedPassword);

            $sessionInvalidated = false;
            if ($command->refreshRememberToken) {
                $updatedUser = $updatedUser->withRememberToken(null);
                $sessionInvalidated = true;
            }

            $this->userRepository->save($updatedUser);

            Log::info('Password updated successfully', $this->sanitizePayload([
                'user_id' => $command->userId,
                'tenant_id' => $command->requestingTenantId,
                'session_invalidated' => $sessionInvalidated,
            ]));

            $this->eventBus->dispatch(
                UserPasswordChanged::now(
                    userId: $command->userId,
                    tenantId: $command->requestingTenantId,
                    sessionInvalidated: $sessionInvalidated,
                )
            );
        });
    }
}
