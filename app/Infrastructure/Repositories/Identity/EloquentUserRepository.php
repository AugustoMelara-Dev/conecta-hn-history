<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Identity;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Persistence\Mappers\UserMapper;
use App\Infrastructure\Scope\TenantContext;
use App\Models\User;

/**
 * EloquentUserRepository - Infrastructure Adapter for User Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  THE ELOQUENT WALL: Eloquent Models NEVER escape this class.            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - All public methods return UserEntity or primitives                    ║
 * ║  - TenantContext enforced on all write operations                        ║
 * ║  - UserMapper translates between ORM and Domain                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * Find a user by primary identifier.
     */
    public function findById(int $id): ?UserEntity
    {
        $model = User::find($id);

        if ($model === null) {
            return null;
        }

        return UserMapper::toEntity($model);
    }

    /**
     * Find a user by email address.
     */
    public function findByEmail(string $email): ?UserEntity
    {
        $model = User::where('email', $email)->first();

        if ($model === null) {
            return null;
        }

        return UserMapper::toEntity($model);
    }

    public function existsByEmail(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    /**
     * Create a new user entity.
     *
     * SECURITY: TenantContext is the authority for tenant_id.
     * Any tenant_id in $attributes is IGNORED to prevent spoofing.
     *
     * @param array<string, mixed> $attributes
     * @return UserEntity
     */
    public function create(array $attributes): UserEntity
    {
        // SECURITY: Force tenant_id from TenantContext (ignore any spoofed value)
        $tenantId = TenantContext::id();

        $model = User::create(array_merge($attributes, [
            'tenant_id' => $tenantId,
        ]));

        return UserMapper::toEntity($model);
    }

    public function update(UserEntity $user): void
    {
       if (!$user->id) {
           throw new \InvalidArgumentException("Cannot update a user without an ID.");
       }
       
       $model = User::findOrFail($user->id);
       
       $model->update([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value, // LOWERING
            'avatar' => $user->avatar,
            'email_verified_at' => $user->emailVerifiedAt,
            'password' => $user->password,
            // 'tenant_id' => ... immutable.
       ]);
    }

    public function updateAvatarIfMissing(int $userId, string $avatarUrl): bool
    {
        return (bool) User::where('id', $userId)
            ->whereNull('avatar')
            ->update(['avatar' => $avatarUrl]);
    }

    /**
     * Persist changes to a user entity OR Create if new.
     *
     * @param UserEntity $entity The entity to save
     * @return UserEntity Fresh entity reflecting persisted state
     */
    public function save(UserEntity $entity): UserEntity
    {
        // UPSERT LOGIC:
        // If entity has no ID, it's new -> Create.
        // If entity has ID, it's existing -> Update.
        
        if ($entity->id === null) {
            // DELEGATE TO CREATE (Enforcing Context)
            return $this->create([
                'name' => $entity->name,
                'email' => $entity->email,
                'role' => $entity->role->value, // LOWERING
                'password' => $entity->password,
                'avatar' => $entity->avatar,
            ]);
        }
        
        // UPDATE PATH
        $model = User::findOrFail($entity->id);

        $model->update([
            'name' => $entity->name,
            'email' => $entity->email,
            'role' => $entity->role->value, // LOWERING
            'avatar' => $entity->avatar,
            'email_verified_at' => $entity->emailVerifiedAt,
            'password' => $entity->password, // Persist password changes
            // tenant_id is immutable - never update it
        ]);

        // Return fresh entity reflecting persisted state
        return UserMapper::toEntity($model->fresh());
    }
}
