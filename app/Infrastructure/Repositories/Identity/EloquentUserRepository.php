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

    /**
     * Persist changes to a user entity.
     *
     * @param UserEntity $entity The entity to save
     * @return UserEntity Fresh entity reflecting persisted state
     */
    public function save(UserEntity $entity): UserEntity
    {
        $model = User::findOrFail($entity->id);

        $model->update([
            'name' => $entity->name,
            'email' => $entity->email,
            'role' => $entity->role,
            'avatar' => $entity->avatar,
            'email_verified_at' => $entity->emailVerifiedAt,
            // tenant_id is immutable - never update it
        ]);

        // Return fresh entity reflecting persisted state
        return UserMapper::toEntity($model->fresh());
    }
}


