<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Application\Shared\Contracts\EventBusInterface;
use Illuminate\Support\Facades\Log;

/**
 * DeleteUserHandler - Secure User Deletion Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRASH FIXED: Corrected findById signature (requires tenantId).          ║
 * ║  LEAK FIXED: Replaced ModelNotFoundException with UserNotFoundException. ║
 * ║  ATOMICITY: Explicit transaction management via Contract.                ║
 * ║  DEPENDENCY INVERSION: Uses standard Interfaces Only.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class DeleteUserHandler
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventBusInterface $eventBus,
        private TransactionManagerInterface $transactionManager,
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
        // PHASE 1: READ - Load Target User (SIGNATURE COMPLIANT)
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->userRepository->findById($command->userId, $command->requestingTenantId);

        if ($user === null) {
            // Updated to use Domain Factory
            throw UserNotFoundException::withIdAndTenant(
                (string) $command->userId,
                $command->requestingTenantId
            );
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: GUARD - IDOR PROTECTION
        // ═══════════════════════════════════════════════════════════════════
        if ($user->tenantId !== $command->requestingTenantId) {
            Log::warning('SECURITY: Cross-tenant user deletion attempt blocked', $this->sanitizePayload([
                'target_user_id' => $command->userId,
                'target_tenant_id' => $user->tenantId,
                'requesting_tenant_id' => $command->requestingTenantId,
            ]));

            throw CrossTenantAccessException::forUserDeletion($command->userId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: WRITE - ATOMIC DELETION AND DISPATCH
        // ═══════════════════════════════════════════════════════════════════
        
        $this->transactionManager->transaction(function () use ($user) {
            $userId = $user->id;
            $tenantId = $user->tenantId;
            $email = $user->email;
            $name = $user->name;

            // 1. Delete (Persistence)
            $this->userRepository->delete($user);

            // 2. Dispatch Event (Inside Transaction)
            $this->eventBus->dispatch(UserDeleted::fromSnapshot(
                userId: $userId,
                tenantId: $tenantId,
                email: $email,
                name: $name,
            ));
            
            Log::info('User deleted', $this->sanitizePayload([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]));
        });
    }
}
