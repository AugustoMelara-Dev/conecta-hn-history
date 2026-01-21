<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Infrastructure\Persistence\Mappers\UserMapper;
use App\Infrastructure\Persistence\Models\SocialIdentityModel;

/**
 * EloquentSocialIdentityRepository - Strict Implementation
 * 
 * Enforces explicit dependencies via Injection.
 * Eliminates 'object' ambiguity.
 */
final class EloquentSocialIdentityRepository implements SocialIdentityRepositoryInterface
{
    public function __construct(
        private readonly UserMapper $mapper
    ) {}

    public function findUserByProvider(string $provider, string $providerId): ?UserEntity
    {
        /** @var SocialIdentityModel|null $identity */
        $identity = SocialIdentityModel::with('user')
            ->where('provider_name', $provider)
            ->where('provider_id', $providerId)
            ->first();

        // 1. Guard Clause: No Identity or No User Link
        if ($identity === null || $identity->user === null) {
            return null;
        }

        // 2. Mapper Translation (Infrastructure -> Domain)
        return $this->mapper->toDomain($identity->user);
    }

    public function linkIdentity(
        UserEntity $user,
        string $provider,
        string $providerId,
        ?string $token = null,
        ?string $avatar = null
    ): void {
        // ENCAPSULATION RESPECT: Use Getters ($user->id()), never properties.
        SocialIdentityModel::updateOrCreate(
            [
                'provider_name' => $provider,
                'provider_id' => $providerId,
            ],
            [
                'user_id' => $user->id(), // Strict UUID from Domain
                'user_type' => 'App\Models\User', // Optional polymorphic support if needed, assuming standard FK for now
                'token' => $token,
                'avatar' => $avatar,
            ]
        );
    }
}
