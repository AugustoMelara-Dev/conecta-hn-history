<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Enums\UserRole;
use App\Models\User as UserModel;
use Carbon\Carbon;

/**
 * UserMapper - Bidirectional Data Translator (Shielded)
 *
 * Enforces strict type boundaries between Eloquent and Domain.
 * - Unknown roles fallback securely to CUSTOMER object.
 * - Enums are explicitly degraded to scalars for persistence.
 */
final class UserMapper
{
    public static function toEntity(UserModel $model): UserEntity
    {
        // LIFTING (Secure Fallback)
        // If Model cast fails or role is missing, we use Enum Object, NOT string.
        $role = $model->role instanceof UserRole 
            ? $model->role 
            : UserRole::CUSTOMER;

        return new UserEntity(
            id: $model->id,
            tenantId: $model->tenant_id ?? 0,
            email: $model->email,
            name: $model->name,
            role: $role, // STRICT TYPE
            password: $model->password ?? null,
            rememberToken: $model->remember_token ?? null,
            avatar: $model->avatar ?? null,
            emailVerifiedAt: $model->email_verified_at 
                ? Carbon::parse($model->email_verified_at) 
                : null,
        );
    }

    public static function toModelArray(UserEntity $entity): array
    {
        $data = [
            'id' => $entity->id,
            'tenant_id' => $entity->tenantId,
            'email' => $entity->email,
            'name' => $entity->name,
            'role' => $entity->role->value, // LOWERING (Explicit Scalar)
            'avatar' => $entity->avatar,
            'email_verified_at' => $entity->emailVerifiedAt,
        ];

        if ($entity->password !== null) {
            $data['password'] = $entity->password;
        }

        if ($entity->rememberToken !== null) {
            $data['remember_token'] = $entity->rememberToken;
        }

        return $data;
    }
}
