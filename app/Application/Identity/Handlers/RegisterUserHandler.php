<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


use App\Application\Identity\Results\RegisterUserResult;

/**
 * RegisterUserHandler - Command Handler for Simple User Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal + Transactional Outbox       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO DB::transaction (handled by Bus Middleware)                        ║
 * ║  - NO App\Models imports (uses Repository interfaces only)              ║
 * ║  - Uses UserRegistered (Domain) instead of Registered (Framework)       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RegisterUserHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
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
            'password' => Hash::make($command->password),
        ]);

        // 2. Create implicit Tenant for the User
        $tenantName = $command->tenantName ?? $command->name . "'s Store";
        $slug = $command->slug ?? Str::slug($tenantName);
        
        $tenant = $this->tenantRepository->create([
            'name' => $tenantName,
            'slug' => Str::lower($slug),
            'user_id' => $user->id,
            'plan_type' => 'semilla', // Default plan
            'subscription_status' => 'active',
            'brand_color' => '#3b82f6',
            'is_active' => true,
            'product_limit' => 10,
        ]);

        // 3. Dispatch Domain Event (Transactional Outbox)
        // Replaces: event(new Registered($user))
        $this->eventDispatcher->dispatch(new \App\Domain\Identity\Events\UserRegistered(
            userId: $user->id,
            tenantId: $tenant->id,
            email: $command->email,
        ));

        // 4. Audit Log (PII Scrubbed)
        Log::info('User registered successfully', $this->sanitizePayload([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'email' => $command->email, // Will be redacted
        ]));

        return new RegisterUserResult(
            id: $user->id,
            tenantId: $tenant->id,
            email: $user->email,
            requiresVerification: true // Logic: New users always require verification
        );
    }
}
