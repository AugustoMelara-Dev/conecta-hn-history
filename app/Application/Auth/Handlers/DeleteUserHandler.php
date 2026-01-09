<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * DeleteUserHandler - Secure User Deletion Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PIPELINE ARCHITECTURE: Manual DB::transaction removed.                  ║
 * ║  TransactionMiddleware handles ACID atomicity.                           ║
 * ║  ValidationMiddleware handles input validity.                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class DeleteUserHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Handle user deletion command.
     *
     * @param DeleteUserCommand $command
     * @return void
     * @throws ModelNotFoundException If user not found
     * @throws CrossTenantAccessException If tenant mismatch (IDOR attack)
     */
    public function handle(DeleteUserCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: Load Target User
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new ModelNotFoundException("User not found: {$command->userId}");
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: IDOR PROTECTION - Tenant Ownership Validation
        // ═══════════════════════════════════════════════════════════════════
        if ($user->tenant_id !== $command->requestingTenantId) {
            Log::warning('SECURITY: Cross-tenant user deletion attempt blocked', [
                'target_user_id' => $command->userId,
                'target_tenant_id' => $user->tenant_id,
                'requesting_tenant_id' => $command->requestingTenantId,
            ]);

            throw CrossTenantAccessException::forUserDeletion($command->userId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: SNAPSHOT - Capture Data Before Destruction
        // ═══════════════════════════════════════════════════════════════════
        $userId = $user->id;
        $tenantId = $user->tenant_id;
        $email = $user->email;
        $name = $user->name;

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4 & 5: DELETE + EVENT DISPATCH
        // NOTE: TransactionMiddleware ensures this entire method is atomic.
        // ═══════════════════════════════════════════════════════════════════
        
        $this->userRepository->delete($user);

        Log::info('User deleted', $this->sanitizePayload([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]));

        // Dispatch via Outbox Wrapper
        // This writes to 'domain_outbox_events' inside the current transaction
        $this->eventDispatcher->dispatch(UserDeleted::fromSnapshot(
            userId: $userId,
            tenantId: $tenantId,
            email: $email,
            name: $name,
        ));
    }
}
