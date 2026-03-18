<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Identity\Entities\UserEntity;
use App\Models\User as UserModel;
use DateTimeImmutable;

/**
 * UserMapper - Infrastructure <-> Domain Translator
 *
 * Reconstitutes Domain Entities from Eloquent Models without breaking encapsulation.
 */
final class UserMapper
{
    public static function toDomain(UserModel $model): UserEntity
    {
        // Using existing fromStateArray to respect Entity Encapsulation
        // Maps Eloquent attributes to Domain State
        return UserEntity::fromStateArray([
            'id' => (string) $model->id,
            'name' => (string) $model->name,
            'email' => (string) $model->email,
            'password' => (string) $model->password,
            'role' => (string) $model->getRawOriginal('role'),
            'tenant_id' => (string) $model->tenant_id,
            'avatar' => $model->avatar,
            'created_at' => $model->created_at?->format(DateTimeImmutable::ATOM),
            'updated_at' => $model->updated_at?->format(DateTimeImmutable::ATOM),
        ]);
    }

    /**
     * Note: toEloquent is handled explicitly in Repository save()
     * to allow granular control over "New vs Existing" state.
     */
}
