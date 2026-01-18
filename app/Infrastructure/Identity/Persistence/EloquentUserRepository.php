<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Persistence;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Models\User;
use DateTimeImmutable;

/**
 * EloquentUserRepository - Infrastructure Layer User Persistence
 *
 * FIXED: Updated method signatures to match UserRepositoryInterface.
 * - save() now returns void
 * - findById() now accepts (string $id, string $tenantId)
 * - findByEmail() now accepts (string $email, string $tenantId)
 * - updateAvatarIfMissing() now accepts string $userId
 * - Added delete(UserEntity) method
 */
final class EloquentUserRepository implements UserRepositoryInterface
{
    public function save(UserEntity $user): void
    {
        if ($user->id) {
            $model = User::where('id', $user->id)->first();
            
            if ($model) {
                $model->name = $user->name;
                $model->email = $user->email;
                $model->password = $user->password;
                
                if ($user->tenantId !== null) {
                    $model->tenant_id = $user->tenantId;
                }
                
                $model->save();
                return;
            }
        }
        
        // Create new user
        User::create([
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'tenant_id' => $user->tenantId,
        ]);
    }

    public function findById(string $id, string $tenantId): ?UserEntity
    {
        $model = User::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
            
        return $model ? $this->toEntity($model) : null;
    }

    public function findByEmail(string $email, string $tenantId): ?UserEntity
    {
        $model = User::where('email', $email)
            ->where('tenant_id', $tenantId)
            ->first();
            
        return $model ? $this->toEntity($model) : null;
    }

    public function updateAvatarIfMissing(string $userId, string $avatarUrl): bool
    {
        $user = User::find($userId);
        
        if ($user && empty($user->avatar_url)) {
            $user->update(['avatar_url' => $avatarUrl]);
            return true;
        }
        
        return false;
    }

    public function delete(UserEntity $user): void
    {
        if ($user->id === null) {
            return;
        }
        
        User::where('id', $user->id)->delete();
    }

    private function toEntity(User $model): UserEntity
    {
        $tenantId = null;
        
        $rawTenantId = $model->tenant_id;
        
        if ($rawTenantId instanceof \App\Domain\Shared\ValueObjects\TenantId) {
            $tenantId = (string) $rawTenantId->value;
        } elseif (is_numeric($rawTenantId)) {
            $tenantId = (string) $rawTenantId;
        } elseif (is_string($rawTenantId)) {
            $tenantId = $rawTenantId;
        }

        return new UserEntity(
            id: (string) $model->id,
            name: $model->name,
            email: $model->email,
            password: $model->password,
            tenantId: $tenantId,
            createdAt: $model->created_at instanceof \DateTime 
                ? DateTimeImmutable::createFromMutable($model->created_at) 
                : new DateTimeImmutable($model->created_at ?? 'now'),
            updatedAt: $model->updated_at instanceof \DateTime 
                ? DateTimeImmutable::createFromMutable($model->updated_at) 
                : new DateTimeImmutable($model->updated_at ?? 'now'),
        );
    }
}
