<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Identity\Entities\UserEntity;
use App\Models\User;
use Carbon\Carbon;

/**
 * UserMapper - Bidirectional Data Translator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ACL BOUNDARY: Translates between Eloquent ORM and Domain Entities.     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Eloquent Models NEVER leave Infrastructure layer                      ║
 * ║  - Domain Entities are pure, serializable, ORM-agnostic                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UserMapper
{
    /**
     * Transform Eloquent Model to Domain Entity.
     *
     * @param User $model The Eloquent model from database
     * @return UserEntity Pure domain entity
     */
    public static function toEntity(User $model): UserEntity
    {
        return new UserEntity(
            id: $model->id,
            tenantId: $model->tenant_id ?? 0,
            email: $model->email,
            name: $model->name,
            role: $model->role ?? 'customer',
            password: $model->password ?? null,
            rememberToken: $model->remember_token ?? null,
            avatar: $model->avatar ?? null,
            emailVerifiedAt: $model->email_verified_at 
                ? Carbon::parse($model->email_verified_at) 
                : null,
        );
    }

    /**
     * Transform Domain Entity to persistence array.
     *
     * @param UserEntity $entity The domain entity
     * @return array<string, mixed> Data for Eloquent::create/update
     */
    public static function toModelArray(UserEntity $entity): array
    {
        $data = [
            'id' => $entity->id,
            'tenant_id' => $entity->tenantId,
            'email' => $entity->email,
            'name' => $entity->name,
            'role' => $entity->role,
            'avatar' => $entity->avatar,
            'email_verified_at' => $entity->emailVerifiedAt,
        ];

        // Only include password if set (avoid nullifying existing password)
        if ($entity->password !== null) {
            $data['password'] = $entity->password;
        }

        // Only include remember_token if set
        if ($entity->rememberToken !== null) {
            $data['remember_token'] = $entity->rememberToken;
        }

        return $data;
    }
}
