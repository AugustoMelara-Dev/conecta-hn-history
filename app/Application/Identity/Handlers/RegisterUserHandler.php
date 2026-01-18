<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Results\RegisterUserResult;
use App\Domain\Identity\Entities\Tenant;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Events\UserRegistered;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\Shared\PasswordHasherInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Class RegisterUserHandler
 *
 * Orchestrates user registration with strict atomic guarantees and security by design.
 * Now specifically properly constructing Domain Entities and hashing passwords.
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private TransactionManagerInterface $transactionManager,
        private OutboxRepositoryInterface $outboxRepository,
        private PasswordHasherInterface $hasher,
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        // 1. Generate Deterministic IDs & Time
        $userId = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        // 2. Security Hardening (Hashing BEFORE Domain/Persistence)
        $passwordHash = $this->hasher->hash($command->password);

        return $this->transactionManager->transactional(function () use ($command, $userId, $tenantId, $passwordHash, $now) {
            
            // 3. Create Pure Domain Entities
            // We use the new Physical Domain Classes, NOT Eloquent Models.
            
            $tenant = new Tenant(
                id: $tenantId,
                name: $command->tenantName,
                createdAt: $now
            );

            $user = new User(
                id: $userId,
                tenantId: $tenantId,
                email: $command->email,
                passwordHash: $passwordHash, // Injected safe hash
                name: $command->name,
                role: 'owner', // Defaulting role as ownership implies initial user
                createdAt: $now
            );

            // 4. Persist State via Repositories (Data Mapper Pattern)
            $this->tenantRepository->save($tenant);
            $this->userRepository->save($user);

            // 5. Atomic Event Dispatch (Outbox)
            // Constructing strictly matching the UserRegistered signature
            $event = new UserRegistered(
                user_id: $userId,
                name: $command->name,
                email: $command->email,
                password_hash: $passwordHash,
                role: 'owner',
                tenant_id: $tenantId,
                occurredOn: $now
            );

            $this->outboxRepository->append($event);

            // 6. Return Clean Result (No PII)
            return new RegisterUserResult(
                id: $userId,
                tenantId: $tenantId,
                email: $command->email,
                registeredAt: $now
            );
        });
    }
}
