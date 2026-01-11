<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Results\RegisterUserResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\TenantGovernance\Factories\TenantFactory;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * RegisterUserHandler - Command Handler for Simple User Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal + Transactional Outbox       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO DB::transaction (handled by Bus Middleware)                        ║
 * ║  - NO App\Models imports (uses Repository interfaces only)              ║
 * ║  - NO HARDCODED LOGIC (delegates to TenantFactory)                      ║
 * ║  - INFRASTRUCTURE AGNOSTIC (injects HasherInterface)                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RegisterUserHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
        private readonly HasherInterface $hasher,
        private readonly TenantFactory $tenantFactory,
    ) {}

    /**
     * Handle the user registration command.
     *
     * @param RegisterUserCommand $command
     * @return RegisterUserResult
     */
    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        // 1. Create User via Repository
        $user = $this->userRepository->create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => $this->hasher->make($command->password),
        ]);

        // 2. Create implicit Tenant via Domain Factory
        $tenantData = $this->tenantFactory->createTrialTenant(
            userId: $user->id,
            name: $command->name,
            email: $command->email
        );
        
        $tenant = $this->tenantRepository->create($tenantData);

        // 3. Dispatch Domain Event (Transactional Outbox)
        $this->eventDispatcher->dispatch(new \App\Domain\Identity\Events\UserRegistered(
            userId: $user->id,
            tenantId: $tenant->id,
            email: $command->email,
        ));

        // 4. Audit Log (PII Scrubbed)
        Log::info('User registered successfully', $this->sanitizePayload([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'email' => $command->email,
        ]));

        return new RegisterUserResult(
            id: $user->id,
            tenantId: $tenant->id,
            email: $user->email,
            requiresVerification: true
        );
    }
}
