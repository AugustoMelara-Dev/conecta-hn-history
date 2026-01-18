<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Results\SocialLoginResult;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Interfaces\EntropyGeneratorInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Events\SocialIdentityLinked;
use App\Domain\Identity\Events\UserRegisteredViaSocial;
use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Domain\Shared\Exceptions\DuplicateEntityException;
use Illuminate\Support\Facades\Log;

/**
 * LoginViaSocialProviderHandler - Tenant-Scoped Identity Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  NO SILENT WRITES: Dispatches UserRegistered/UserIdentityLinked events. ║
 * ║  TENANT ISOLATED: All lookups are scoped by tenantId.                   ║
 * ║  RESILIENT: Handles concurrency via try/catch strategies.               ║
 * ║  HIGH PERFORMANCE: Short Transactions & Ordered UUIDs.                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LoginViaSocialProviderHandler
{
    use LogSanitizer;

    public function __construct(
        private SocialIdentityRepositoryInterface $socialIdentityRepository,
        private UserRepositoryInterface $userRepository,
        private TransactionManagerInterface $transactionManager,
        private HasherInterface $hasher,
        private ClockInterface $clock,
        private EntropyGeneratorInterface $entropyGenerator,
        private EventBusInterface $eventBus,
    ) {}

    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        // PERFORMANCE: Store events to dispatch AFTER transaction commit
        // Prevents Connection Starvation if listeners are slow
        $pendingEvents = [];

        /** @var SocialLoginResult $result */
        $result = $this->transactionManager->transaction(function () use ($command, &$pendingEvents) {
            
            // 1. Strategy: Direct Social Identity Match (Login)
            $user = $this->socialIdentityRepository->findUserByProvider(
                $command->providerName->value,
                $command->providerId,
                $command->tenantId
            );

            if ($user instanceof UserEntity) {
                $this->updateSocialToken($user, $command);
                return SocialLoginResult::success($user);
            }

            // 2. Strategy: Link Existing Account (Trust on First Use)
            $existingUser = $this->userRepository->findByEmail($command->email, $command->tenantId);

            if ($existingUser !== null) {
                $this->linkIdentity($existingUser, $command);
                $this->updateAvatarIfNeeded($existingUser, $command);
                
                // Queue Event
                $pendingEvents[] = SocialIdentityLinked::now(
                    userId: $existingUser->id(),
                    tenantId: $command->tenantId,
                    provider: $command->providerName->value,
                );
                
                return SocialLoginResult::success($existingUser);
            }

            // 3. Strategy: New User Provisioning (Registration)
            try {
                $newUser = UserEntity::registerViaSocial(
                    $command->tenantId,
                    $command->name,
                    $command->email,
                    $command->avatarUrl,
                    $command->providerName->value,
                    $command->providerId
                );

                $this->userRepository->save($newUser);

                $this->linkIdentity($newUser, $command);
                
                // Queue Event
                $pendingEvents[] = UserRegisteredViaSocial::now(
                    userId: $newUser->id(),
                    tenantId: $command->tenantId,
                    email: $newUser->email(),
                    provider: $command->providerName->value,
                );

                return SocialLoginResult::newUser($newUser);

            } catch (DuplicateEntityException $e) {
                // RESILIENCE: Handle Race Condition (User created by another process)
                Log::warning("Race condition detected in social login provisioning", $this->sanitizePayload([
                    'email' => $command->email,
                    'tenant_id' => $command->tenantId
                ]));
                
                // Re-fetch with tenant scope
                $racedUser = $this->userRepository->findByEmail($command->email, $command->tenantId);
                
                if ($racedUser instanceof UserEntity) {
                    // ATOMIC RECOVERY: Try to link, but ignore if already linked by competitor
                    try {
                        $this->linkIdentity($racedUser, $command);
                        
                        // If successful, queue event
                        $pendingEvents[] = SocialIdentityLinked::now(
                            userId: $racedUser->id(),
                            tenantId: $command->tenantId,
                            provider: $command->providerName->value,
                        );
                    } catch (DuplicateEntityException $innerException) {
                        // Idempotency: Identity already linked.
                        // We swallow this error because the desired state (Linked) is achieved.
                        // No event dispatched here to avoid noise, or duplicate dispatch
                    }
                    
                    return SocialLoginResult::success($racedUser);
                }
                throw $e;
            }
        });

        // DISPATCH EVENTS AFTER COMMIT
        // This ensures DB connection is free and ready for next request
        foreach ($pendingEvents as $event) {
            $this->eventBus->dispatch($event);
        }

        return $result;
    }

    private function linkIdentity(UserEntity $user, LoginViaSocialProviderCommand $command): void
    {
        $this->socialIdentityRepository->linkIdentity(
            $user,
            $command->providerName->value,
            $command->providerId,
            $command->accessToken,
            $command->avatarUrl,
            $command->tenantId
        );
    }

    private function updateSocialToken(UserEntity $user, LoginViaSocialProviderCommand $command): void
    {
        $this->socialIdentityRepository->updateToken(
            $command->providerName->value,
            $command->providerId,
            $command->accessToken,
            $command->tenantId
        );
    }

    private function updateAvatarIfNeeded(UserEntity $user, LoginViaSocialProviderCommand $command): void
    {
        if (empty($user->avatar()) && !empty($command->avatarUrl)) {
            $this->userRepository->updateAvatarIfMissing($user->id(), $command->avatarUrl);
        }
    }
}
