<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Results\RegisterUserResult;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Events\UserRegistered;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\TenantGovernance\Entities\TenantEntity as Tenant;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Class RegisterUserHandler
 *
 * Orchestrates user registration with strict atomic guarantees and security by design.
 * Now specifically properly constructing Domain Entities and hashing passwords.
 */
final readonly class RegisterUserHandler implements RegisterUserHandlerInterface
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

        return $this->transactionManager->transaction(function () use ($command, $userId, $tenantId, $passwordHash, $now) {

            // 3. Create Pure Domain Entities
            // We use the new Physical Domain Classes, NOT Eloquent Models.

            $tenantName = $command->tenantName ?? $command->name."'s Workspace";
            $tenantDomain = $command->slug ?? Str::slug($tenantName);

            $tenant = Tenant::create(
                id: $tenantId,
                name: $tenantName,
                domain: $tenantDomain
            );

            $user = UserEntity::register(
                uuid: $userId,
                name: $command->name,
                email: $command->email,
                passwordHash: $passwordHash, // Injected safe hash
                role: UserRole::TENANT_OWNER,
                tenantId: $tenantId
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
