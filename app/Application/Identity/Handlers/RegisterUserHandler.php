<?php

declare(strict_types=1);

namespace App\Application\Identity\Handlers;

use App\Application\Common\Interfaces\ClientContextProviderInterface;
use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Results\RegisterUserResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Enums\UserRole;
use App\Application\Identity\Interfaces\UserRepositoryInterface; // Updated Interface Path
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\Tenancy\Entities\TenantEntity; // Updated Entity Path
use App\Application\Tenancy\Interfaces\TenantRepositoryInterface; // Checked Interface Path
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RegisterUserHandler - Titanium Atomic Orquestrator
 * 
 * - Injects Business Rules (Default Plan).
 */
final readonly class RegisterUserHandler implements RegisterUserHandlerInterface
{
    use LogSanitizer;

    private const DEFAULT_PLAN = 'SEED';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private HasherInterface $hasher,
        private ClientContextProviderInterface $contextProvider
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        Log::info('Registering User & Tenant (Atomic)', $this->sanitizePayload(['email' => $command->email]));

        $hashedPassword = $this->hasher->make($command->password);
        
        $tenantName = $command->tenantName ?? ($command->name . "'s Store");
        $tenantDomain = $command->slug ?? Str::slug($tenantName);

        // 1. IDENTITY PRE-ALLOCATION
        $userId = Str::uuid()->toString();
        $tenantId = Str::uuid()->toString();

        // 2. IN-MEMORY INSTANTIATION
        
        // Create Tenant Aggregate (With Injection of Business Rule)
        $tenant = TenantEntity::create(
            id: $tenantId,
            name: $tenantName,
            domain: $tenantDomain,
            plan: self::DEFAULT_PLAN
        );

        // Create User Aggregate
        $user = UserEntity::register(
            uuid: $userId,
            name: $command->name,
            email: $command->email,
            passwordHash: $hashedPassword,
            role: UserRole::OWNER,
            tenantId: $tenantId
        );

        // 3. PERSISTENCE (Atomic Dual-Write)
        DB::transaction(function () use ($user, $tenant) {
            $this->tenantRepository->save($tenant);
            $this->userRepository->save($user);
        });

        // 4. RETURN RESULT
        return new RegisterUserResult(
            id: (string) $user->id(),
            tenantId: (string) $tenant->id(),
            email: $user->email(),
            requiresVerification: true
        );
    }
}
