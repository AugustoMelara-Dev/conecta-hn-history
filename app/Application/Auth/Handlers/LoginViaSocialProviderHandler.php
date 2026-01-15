<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Results\SocialLoginResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\Security\Contracts\TokenGeneratorInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Interfaces\EntropyGeneratorInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * LoginViaSocialProviderHandler - Pure Identity Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONTEXT ISOLATION: No Tenant logic. Identity creation only.            ║
 * ║  PROTOCOL AGNOSTIC: Returns pure DTOs, no URLs.                         ║
 * ║  RESILIENT: Handles concurrency via try/catch strategies.               ║
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
        private EntropyGeneratorInterface $entropyGenerator
    ) {}

    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        return $this->transactionManager->transaction(function () use ($command) {
            
            // 1. Strategy: Direct Social Identity Match
            $user = $this->socialIdentityRepository->findUserByProvider(
                $command->providerName->value,
                $command->providerId
            );

            if ($user !== null) {
                $this->updateSocialToken($user, $command); // Side Effect: Refresh Token
                return SocialLoginResult::success($this->mapToEntity($user));
            }

            // 2. Strategy: Link Existing Account (Trust on First Use)
            $existingUser = $this->userRepository->findByEmail($command->email);

            if ($existingUser !== null) {
                $this->linkIdentity($existingUser, $command);
                $this->updateAvatarIfNeeded($existingUser, $command); // Logic in memory, then persist
                return SocialLoginResult::success($this->mapToEntity($existingUser));
            }

            // 3. Strategy: New User Provisioning
            try {
                $newUser = $this->provisionNewIdentity($command);
                return SocialLoginResult::newUser($this->mapToEntity($newUser));
            } catch (UniqueConstraintViolationException $e) {
                // Race condition: User created by another process in the microseconds between check and insert
                Log::warning("Race condition detected in social login provisioning", ['email' => $command->email]);
                
                $racedUser = $this->userRepository->findByEmail($command->email);
                if ($racedUser) {
                    $this->linkIdentity($racedUser, $command);
                    return SocialLoginResult::success($this->mapToEntity($racedUser));
                }
                throw $e;
            }
        });
    }

    private function provisionNewIdentity(LoginViaSocialProviderCommand $command): object
    {
        // Generate secure random password as placeholder
        $securePassword = $this->entropyGenerator->generateString(32);
        
        $user = $this->userRepository->create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => $this->hasher->make($securePassword),
            'email_verified_at' => $this->clock->now(),
            'avatar' => $command->avatarUrl,
        ]);

        $this->socialIdentityRepository->linkIdentity(
            $user,
            $command->providerName->value,
            $command->providerId,
            $command->accessToken,
            $command->avatarUrl
        );

        Log::info('New social identity provisioned', $this->sanitizePayload([
            'user_id' => $user->id,
            'provider' => $command->providerName->value
        ]));

        return $user;
    }

    private function linkIdentity(object $user, LoginViaSocialProviderCommand $command): void
    {
        $this->socialIdentityRepository->linkIdentity(
            $user,
            $command->providerName->value,
            $command->providerId,
            $command->accessToken,
            $command->avatarUrl
        );
    }

    private function updateSocialToken(object $user, LoginViaSocialProviderCommand $command): void
    {
        // Optimistic update, non-critical
        $this->socialIdentityRepository->updateToken(
            $command->providerName->value,
            $command->providerId,
            $command->accessToken
        );
    }

    private function updateAvatarIfNeeded(object $user, LoginViaSocialProviderCommand $command): void
    {
        if (empty($user->avatar) && !empty($command->avatarUrl)) {
            // Memory update
            $user->avatar = $command->avatarUrl;
            // Persistence
            $this->userRepository->save($user);
        }
    }

    private function mapToEntity(object $eloquentUser): UserEntity
    {
        return new UserEntity(
            id: $eloquentUser->id,
            tenantId: $eloquentUser->tenant_id ?? null,
            email: $eloquentUser->email,
            name: $eloquentUser->name,
            role: $eloquentUser->role ?? 'user', // Default role
        );
    }
}
