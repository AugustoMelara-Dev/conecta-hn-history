<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Persistence;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Models\User;
use DateTimeImmutable;

final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?UserEntity
    {
        $model = User::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function findByEmail(string $email): ?UserEntity
    {
        $model = User::where('email', $email)->first();
        return $model ? $this->toEntity($model) : null;
    }

    public function existsByEmail(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public function create(array $attributes): UserEntity
    {
        $model = User::create($attributes);
        return $this->toEntity($model);
    }

    /**
     * Persist entity mutations to Infrastructure.
     */
    public function update(UserEntity $user): void
    {
        if ($user->id === null) {
            return;
        }

        // Use findOrFail for strict integrity check per specification
        $model = User::findOrFail($user->id);
        
        $model->name = $user->name;
        $model->email = $user->email;
        $model->password = $user->password;
        
        // Critical Update: Link Tenant
        if ($user->tenantId !== null) {
            $model->tenant_id = $user->tenantId;
        }
        
        $model->save();
    }

    public function save(UserEntity $entity): UserEntity
    {
        // Polymorphic save (create or update)
        if ($entity->id) {
            $this->update($entity);
            return $this->findById($entity->id);
        }
        return $this->create([
            'name' => $entity->name,
            'email' => $entity->email,
            'password' => $entity->password,
        ]);
    }

    public function updateAvatarIfMissing(int $userId, string $avatarUrl): bool
    {
        $user = User::find($userId);
        if ($user && empty($user->avatar_url)) {
            $user->update(['avatar_url' => $avatarUrl]);
            return true;
        }
        return false;
    }

    private function toEntity(User $model): UserEntity
    {
        $tenantId = null;
        
        // Handle TenantId Value Object (from TenantIdCast)
        $rawTenantId = $model->tenant_id;
        
        if ($rawTenantId instanceof \App\Domain\Shared\ValueObjects\TenantId) {
            $tenantId = $rawTenantId->value;
        } elseif (is_numeric($rawTenantId)) {
            $tenantId = (int) $rawTenantId;
        }

        return new UserEntity(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            password: $model->password,
            tenantId: $tenantId,
            createdAt: $model->created_at instanceof \DateTime ? DateTimeImmutable::createFromMutable($model->created_at) : new DateTimeImmutable($model->created_at ?? 'now'),
            updatedAt: $model->updated_at instanceof \DateTime ? DateTimeImmutable::createFromMutable($model->updated_at) : new DateTimeImmutable($model->updated_at ?? 'now'),
        );
    }
}
