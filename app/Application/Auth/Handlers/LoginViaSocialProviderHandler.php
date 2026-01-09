<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\DTOs\Auth\SocialLoginResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Events\SocialIdentityLinked;
use App\Domain\Identity\Events\UserRegistered;
use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Domain\Identity\Repositories\TenantRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LoginViaSocialProviderHandler - Identity Federation Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SOCIALITE AGNOSTIC: Zero Laravel\Socialite imports.                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  TRI-STATE RESOLUTION:                                                   ║
 * ║  A) User exists + linked → Direct login                                 ║
 * ║  B) User exists by email → Link identity (TOFU)                         ║
 * ║  C) User not found → Full registration (User + Tenant)                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  STRICT RETURN: SocialLoginResult DTO with isNewUser flag               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LoginViaSocialProviderHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly SocialIdentityRepositoryInterface $socialIdentityRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Handle social login command.
     *
     * @param LoginViaSocialProviderCommand $command
     * @return SocialLoginResult
     */
    public function handle(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        // ═══════════════════════════════════════════════════════════════════
        // SCENARIO A: Direct Identity Resolution
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->socialIdentityRepository->findUserByProvider(
            $command->providerName,
            $command->providerId
        );

        if ($user !== null) {
            // Update token for API calls
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

        // ═══════════════════════════════════════════════════════════════════
        // SCENARIO B: Account Linking (Trust On First Use)
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->userRepository->findByEmail($command->email);

        if ($user !== null) {
            // Link the new social identity
            $this->socialIdentityRepository->linkIdentity(
                $user,
                $command->providerName,
                $command->providerId,
                $command->accessToken,
                $command->avatarUrl
            );

            // Update avatar if missing (using wither pattern for immutability)
            if (empty($user->avatar) && $command->avatarUrl) {
                $user = $user->withAvatar($command->avatarUrl);
                $user = $this->userRepository->save($user);
            }

            // Dispatch audit event
            $this->eventDispatcher->dispatch(SocialIdentityLinked::create(
                userId: $user->id,
                providerName: $command->providerName,
            ));

            Log::info('Social login: identity linked', $this->sanitizePayload([
                'user_id' => $user->id,
                'provider' => $command->providerName,
            ]));

            return SocialLoginResult::forExistingUser($user);
        }

        // ═══════════════════════════════════════════════════════════════════
        // SCENARIO C: Full Registration (User + Tenant)
        // ═══════════════════════════════════════════════════════════════════
        return $this->registerNewUser($command);
    }

    /**
     * Register a completely new user from social identity.
     */
    private function registerNewUser(LoginViaSocialProviderCommand $command): SocialLoginResult
    {
        // 1. Create Tenant first
        $teamName = $this->generateTeamName($command->name);
        $tenant = $this->tenantRepository->create([
            'name' => $teamName,
            'slug' => Str::slug($teamName) . '-' . Str::random(4),
            'status' => 'active',
        ]);

        // 2. Create User with secure random password
        $user = $this->userRepository->create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(), // Trusted provider
            'avatar' => $command->avatarUrl,
            'tenant_id' => $tenant->id,
        ]);

        // 3. Link Social Identity
        $this->socialIdentityRepository->linkIdentity(
            $user,
            $command->providerName,
            $command->providerId,
            $command->accessToken,
            $command->avatarUrl
        );

        // 4. Dispatch registration event
        $this->eventDispatcher->dispatch(new UserRegistered(
            userId: $user->id,
            tenantId: $tenant->id,
            email: $command->email,
            source: 'social:' . $command->providerName,
        ));

        Log::info('Social login: new user registered', $this->sanitizePayload([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'provider' => $command->providerName,
        ]));

        return SocialLoginResult::forNewUser($user, $tenant);
    }

    /**
     * Generate team name from user name.
     */
    private function generateTeamName(string $userName): string
    {
        $firstName = explode(' ', trim($userName), 2)[0];
        return $firstName . "'s Store";
    }
}

