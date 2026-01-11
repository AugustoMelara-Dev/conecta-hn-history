<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Results\SocialLoginResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Enums\RegistrationSource;
use App\Domain\Identity\Events\SocialIdentityLinked;
use App\Domain\Identity\Events\UserRegistered;
use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\Security\Contracts\TokenGeneratorInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\Contracts\TransactionManagerInterface;
use App\Domain\TenantGovernance\Factories\TenantFactory;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LoginViaSocialProviderHandler - Identity Federation Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SRE GRADE: HIGH AVAILABILITY & DURABILITY COMPLIANT                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - ATOMICITY: Enforced via TransactionManagerInterface.                 ║
 * ║  - CONCURRENCY: Handled via atomic SQL updates (Blind Writes).          ║
 * ║  - DETERMINISM: Time and Entropy injected via Interfaces.               ║
 * ║  - OBSERVABILITY: Strict Log Sanitization.                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LoginViaSocialProviderHandler
{
    use LogSanitizer;

    public function __construct(
        private SocialIdentityRepositoryInterface $socialIdentityRepository,
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private TransactionalEventDispatcher $eventDispatcher,
        private HasherInterface $hasher,
        private TenantFactory $tenantFactory,
        private TransactionManagerInterface $transactionManager,
        private ClockInterface $clock,
        private TokenGeneratorInterface $tokenGenerator
    ) {}

    /**
     * Handle social login orchestration with strict ACID guarantees.
     *
     * @param LoginViaSocialProviderCommand $command
     * @return SocialLoginResult
     * @throws Throwable
     */
    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        return $this->transactionManager->transaction(function () use ($command) {
            return $this->processLogin($command);
        });
    }

    private function processLogin(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        // 1. STRATEGY A: DIRECT IDENTITY RESOLUTION
        $user = $this->socialIdentityRepository->findUserByProvider(
            $command->providerName,
            $command->providerId
        );

        if ($user !== null) {
            $this->socialIdentityRepository->updateToken(
                $command->providerName,
                $command->providerId,
                $command->accessToken
            );

            Log::info('Social login: existing identity', $this->sanitizePayload([
                'user_id' => $user->id,
                'provider' => $command->providerName,
            ]));

            return SocialLoginResult::forExistingUser($user);
        }

        // 2. STRATEGY B: ACCOUNT LINKING (TRUST-ON-FIRST-USE)
        $user = $this->userRepository->findByEmail($command->email);

        if ($user !== null) {
            $this->socialIdentityRepository->linkIdentity(
                $user,
                $command->providerName,
                $command->providerId,
                $command->accessToken,
                $command->avatarUrl
            );

            // SRE REQUIREMENT: Atomic Blind Update for Concurrency Safety
            if ($command->avatarUrl) {
                $this->userRepository->updateAvatarIfMissing($user->id, $command->avatarUrl);
            }

            // Dispatch Domain Event
            $this->eventDispatcher->dispatch(SocialIdentityLinked::create(
                userId: $user->id,
                providerName: $command->providerName,
            ));

            return SocialLoginResult::forExistingUser($user);
        }

        // 3. STRATEGY C: FULL PROVISIONING (Refactored for Determinism)
        return $this->provisionNewUser($command);
    }

    private function provisionNewUser(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        // 3.1 Generate Secure Entropy (Deterministically Testable)
        $secureRandomPassword = $this->tokenGenerator->generate(32); 
        $hashedPassword = $this->hasher->make($secureRandomPassword);

        // 3.2 Create User Entity (Time Decoupled)
        $user = $this->userRepository->create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => $hashedPassword,
            'email_verified_at' => $this->clock->now(), // Injected Time
            'avatar' => $command->avatarUrl,
        ]);

        // 3.3 Create Tenant Entity (Via Rich Domain Factory)
        $tenantEntity = $this->tenantFactory->createTrialTenant(
            userId: $user->id,
            name: $command->name,
            source: $command->providerName
        );
        
        // 3.4 Persist Tenant (Pass-through Entity)
        $tenant = $this->tenantRepository->create($tenantEntity);

        // 3.5 Establish Identity Link
        $this->socialIdentityRepository->linkIdentity(
            $user,
            $command->providerName,
            $command->providerId,
            $command->accessToken,
            $command->avatarUrl
        );

        // 3.6 Dispatch Domain Event (Safe from Magic Strings)
        $this->eventDispatcher->dispatch(new UserRegistered(
            userId: $user->id,
            tenantId: $tenant->id,
            email: $command->email,
            source: RegistrationSource::fromProvider($command->providerName), // Safe Enum
        ));

        Log::info('Social login: new user registered', $this->sanitizePayload([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'provider' => $command->providerName,
        ]));

        return SocialLoginResult::forNewUser($user, $tenant);
    }
}
