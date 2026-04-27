<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\UserEntity;

/**
 * SocialIdentityRepositoryInterface - Strict Typing Contract
 *
 * Enforces UserEntity injection. No more 'object' weakness.
 */
interface SocialIdentityRepositoryInterface
{
    /**
     * Find a User by their Social Provider Identity.
     * Returns the pure Domain Entity, not an Eloquent Model.
     */
    public function findUserByProvider(string $provider, string $providerId): ?UserEntity;

    /**
     * Link a Social Identity to a User.
     * Requires valid UserEntity context.
     */
    public function linkIdentity(
        UserEntity $user,
        string $provider,
        string $providerId,
        ?string $token = null,
        ?string $refreshToken = null,
        ?\DateTimeInterface $tokenExpiresAt = null,
        ?string $avatar = null
    ): void;

    public function updateToken(
        string $provider,
        string $providerId,
        ?string $token,
        ?string $refreshToken = null,
        ?\DateTimeInterface $tokenExpiresAt = null,
    ): void;
}
