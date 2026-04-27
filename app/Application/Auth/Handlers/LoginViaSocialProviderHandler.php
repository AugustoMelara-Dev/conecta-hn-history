<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Results\SocialLoginResult;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Events\SocialIdentityLinked;
use App\Domain\Identity\Events\UserRegisteredViaSocial;
use App\Domain\Identity\Exceptions\CrossTenantAccessException;
use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Exceptions\DuplicateEntityException;
use Psr\Log\LoggerInterface;

/**
 * LoginViaSocialProviderHandler - Atomic Protocol Edition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMIC PROTOCOL: "Insert-or-Die" Gatekeeper                            ║
 * ║  CONSISTENCY: Transactional Outbox within Atomic Lock                   ║
 * ║  RESILIENT: Handles User Email Collisions (Unique Index) Gracefully     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LoginViaSocialProviderHandler implements LoginViaSocialProviderHandlerInterface
{
    public function __construct(
        private SocialIdentityRepositoryInterface $socialIdentityRepository,
        private UserRepositoryInterface $userRepository,
        private OutboxRepositoryInterface $outboxRepository,
        private LoggerInterface $logger,
    ) {}

    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        // 1. Strategy: Direct Social Identity Match (Login)
        $user = $this->socialIdentityRepository->findUserByProvider(
            $command->providerName->value,
            $command->providerId
        );

        if ($user instanceof UserEntity) {
            if ($user->tenantId() !== $command->tenantId) {
                throw new CrossTenantAccessException('social identity', (string) $user->id());
            }

            $this->updateSocialToken($user, $command);

            return SocialLoginResult::success($user);
        }

        return $this->processRegistrationOrLink($command);
    }

    private function processRegistrationOrLink(LoginViaSocialProviderCommand $command): SocialLoginResult
    {

        // 2. Strategy: Link Existing Account (Trust on First Use)
        $existingUser = $this->userRepository->findByEmail($command->email, $command->tenantId);

        if ($existingUser !== null) {
            $this->linkIdentity($existingUser, $command);
            $this->updateAvatarIfNeeded($existingUser, $command);

            // Outbox Persistence
            $event = SocialIdentityLinked::now(
                userId: (string) $existingUser->id(),
                tenantId: $command->tenantId,
                provider: $command->providerName->value,
            );

            $this->persistToOutbox($event);

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

            // Outbox Persistence
            $event = UserRegisteredViaSocial::now(
                userId: (string) $newUser->id(),
                tenantId: $command->tenantId,
                email: $newUser->email(),
                provider: $command->providerName->value,
            );

            $this->persistToOutbox($event);

            return SocialLoginResult::newUser($newUser);

        } catch (DuplicateEntityException $e) {
            // RESILIENCE: Handle Race Condition (User created by another process but with different Idempotency Key)
            // OBSERVABILITY: Log the collision for forensic analysis and alerting
            $this->logger->warning(
                'Social Login Race Condition: Duplicate user detected during atomic creation attempt.',
                [
                    'email' => $command->email,
                    'provider' => $command->providerName->value,
                    'tenant_id' => $command->tenantId,
                    'exception_message' => $e->getMessage(),
                    'recovery_action' => 'Returning existing user instance',
                ]
            );

            // Re-fetch with tenant scope
            $racedUser = $this->userRepository->findByEmail($command->email, $command->tenantId);

            if ($racedUser instanceof UserEntity) {
                // ATOMIC RECOVERY: Try to link, but ignore if already linked by competitor
                try {
                    $this->linkIdentity($racedUser, $command);

                    // Outbox Persistence (Recovery)
                    $event = SocialIdentityLinked::now(
                        userId: (string) $racedUser->id(),
                        tenantId: $command->tenantId,
                        provider: $command->providerName->value,
                    );

                    $this->persistToOutbox($event);

                } catch (DuplicateEntityException $innerException) {
                    // Already linked by the other winner.
                }

                return SocialLoginResult::success($racedUser);
            }
            throw $e;
        }
    }

    private function persistToOutbox(object $event): void
    {
        $this->outboxRepository->append($event);
    }

    private function linkIdentity(UserEntity $user, LoginViaSocialProviderCommand $command): void
    {
        $this->socialIdentityRepository->linkIdentity(
            $user,
            $command->providerName->value,
            $command->providerId,
            $command->accessToken,
            $command->refreshToken,
            null,
            $command->avatarUrl
        );
    }

    private function updateSocialToken(UserEntity $user, LoginViaSocialProviderCommand $command): void
    {
        $this->socialIdentityRepository->updateToken(
            $command->providerName->value,
            $command->providerId,
            $command->accessToken,
            $command->refreshToken,
        );
    }

    private function updateAvatarIfNeeded(UserEntity $user, LoginViaSocialProviderCommand $command): void
    {
        if (empty($user->avatar()) && ! empty($command->avatarUrl)) {
            $this->userRepository->updateAvatarIfMissing($user->id(), $command->avatarUrl);
        }
    }
}
