<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Identity;

use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Models\SocialIdentity;

/**
 * EloquentSocialIdentityRepository - Infrastructure Adapter for Social Identity
 */
final class EloquentSocialIdentityRepository implements SocialIdentityRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findUserByProvider(string $provider, string $providerId): ?object
    {
        $identity = SocialIdentity::where('provider_name', $provider)
            ->where('provider_id', $providerId)
            ->first();

        return $identity?->user;
    }

    /**
     * @inheritDoc
     */
    public function linkIdentity(
        object $user,
        string $provider,
        string $providerId,
        ?string $token = null,
        ?string $avatar = null
    ): void {
        SocialIdentity::create([
            'user_id' => $user->id,
            'provider_name' => $provider,
            'provider_id' => $providerId,
            'token' => $token,
            'avatar' => $avatar,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function updateToken(string $provider, string $providerId, ?string $token): void
    {
        SocialIdentity::where('provider_name', $provider)
            ->where('provider_id', $providerId)
            ->update(['token' => $token]);
    }
}
