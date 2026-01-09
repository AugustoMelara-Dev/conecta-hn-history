<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

/**
 * SocialIdentityRepositoryInterface - Domain Port for Social Identity Resolution
 *
 * Provides specialized queries for OAuth identity resolution.
 */
interface SocialIdentityRepositoryInterface
{
    /**
     * Find user by social provider identity.
     *
     * @param string $provider Provider name (google, github, etc.)
     * @param string $providerId Provider's unique user ID
     * @return object|null The User entity or null
     */
    public function findUserByProvider(string $provider, string $providerId): ?object;

    /**
     * Link a social identity to an existing user.
     *
     * @param object $user The User entity
     * @param string $provider Provider name
     * @param string $providerId Provider's unique user ID
     * @param string|null $token OAuth token
     * @param string|null $avatar Avatar URL
     * @return void
     */
    public function linkIdentity(
        object $user,
        string $provider,
        string $providerId,
        ?string $token = null,
        ?string $avatar = null
    ): void;

    /**
     * Update the token for an existing identity.
     *
     * @param string $provider Provider name
     * @param string $providerId Provider's unique user ID
     * @param string|null $token New OAuth token
     * @return void
     */
    public function updateToken(string $provider, string $providerId, ?string $token): void;
}
