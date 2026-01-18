<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Persistence\Mappers\UserMapper;
use App\Models\User as UserModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * EloquentUserRepository - Identity-First Persistence Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDENTITY-FIRST PERSISTENCE: UUIDs are generated in Domain, not DB.     ║
 * ║  STRICT ENCAPSULATION: Uses Getters only. No public property access.    ║
 * ║  TYPE SAFETY: String UUIDs everywhere.                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * Persist User Entity (Upsert Logic).
     * 
     * Handles Pre-generated UUIDs correctly.
     */
    public function save(UserEntity $user): void
    {
        // 1. Identity-First Lookup: Does this UUID exist?
        // We cannot rely on 'id === null' because UUID is pre-generated in Domain Factory.
        $model = UserModel::find($user->id());

        if ($model === null) {
            // 2. CREATE PATH
            $model = new UserModel();
            $model->id = $user->id(); // Explicit Assignment of Domain UUID
            $model->tenant_id = $user->tenantId(); // Immutable Tenant Context
        }

        // 3. HYDRATION (Update State)
        $model->name = $user->name();
        $model->email = $user->email();
        $model->password = $user->password();
        $model->role = $user->role()->value;
        $model->avatar = $user->avatar();
        
        // Note: 'provider' fields belong to SocialIdentity, not User table.
        // If User table has them, they are legacy/denormalized. We skip them here 
        // to respect SRP, as they are handled by SocialIdentityRepository.

        // 4. PERSIST
        try {
            $model->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw new \App\Domain\Shared\Exceptions\DuplicateEntityException(
                "User with ID {$user->id()} or Email {$user->email()} already exists.", 
                0, 
                $e
            );
        }
        
        // Note: No need to return Entity as strict void is requested contract,
        // and Entity is already up-to-date (it's the source of truth).
    }

    public function findById(string $id, string $tenantId): ?UserEntity
    {
        $model = UserModel::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($model === null) {
            return null;
        }

        return UserMapper::toDomain($model);
    }

    public function findByEmail(string $email, string $tenantId): ?UserEntity
    {
        $model = UserModel::where('email', $email)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($model === null) {
            return null;
        }

        return UserMapper::toDomain($model);
    }

    public function updateAvatarIfMissing(string $userId, string $avatarUrl): bool
    {
        // Direct DB update for atomicity/performance on partial updates
        return (bool) UserModel::where('id', $userId)
            ->whereNull('avatar')
            ->update(['avatar' => $avatarUrl]);
    }
    
    public function delete(UserEntity $user): void
    {
        UserModel::destroy($user->id());
    }
}
