<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Persistence;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Models\User;

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
final readonly class EloquentUserRepository implements UserRepositoryInterface
{

    /**
     * Persiste una entidad de dominio UserEntity en la base de datos.
     *
     * PATRÓN DATA MAPPER: Mapeo manual explícito.
     * Cada campo se asigna individualmente - PROHIBIDO usar fill() o create().
     *
     * @param  UserEntity  $user  Agregado de dominio a persistir
     */
    public function save(UserEntity $user): void
    {
        // 1. DETERMINACIÓN INSERT/UPDATE mediante findOrNew
        $model = User::findOrNew($user->id());
        $emailChanged = $model->exists && $model->email !== $user->email();

        // 2. MAPEO MANUAL EXPLÍCITO (Data Mapper Pattern)
        // Cada propiedad se extrae del agregado y se asigna al modelo Eloquent
        $model->id = $user->id();               // UUID pre-generado
        $model->name = $user->name();           // String plano
        $model->email = $user->email();         // String validado en entidad
        $model->password = $user->password();   // Hash ya cocinado
        $model->tenant_id = $user->tenantId();  // UUID del tenant
        $model->role = $user->role()->value;    // Enum -> string
        $model->avatar = $user->avatar();       // Avatar social / perfil
        $model->banned_at = $user->bannedAt();

        // Campos opcionales
        if ($user->rememberToken() !== null) {
            $model->remember_token = $user->rememberToken();
        }

        if ($emailChanged) {
            $model->email_verified_at = null;
        }

        // 3. PERSISTENCIA ATÓMICA
        $model->save();

        // 4. DISPATCH DE EVENTOS DE DOMINIO (Event-Driven Architecture)
        // Titanium Rule: La persistencia INCLUYE la publicación de eventos
        // NOTA: Usamos el dispatcher nativo de Laravel ya que DomainEventInterface
        // es compatible con el sistema de eventos de Laravel.
        foreach ($user->getUncommittedEvents() as $event) {
            event($event);
        }
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

        if ($user && empty($user->avatar)) {
            $user->update(['avatar' => $avatarUrl]);

            return true;
        }

        return false;
    }

    public function existsByEmail(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public function delete(UserEntity $user): void
    {
        $userId = $user->id();

        if ($userId === '') {
            return;
        }

        User::whereKey($userId)->delete();
    }

    public function resetPassword(string $userId, string $tenantId, string $passwordHash, string $rememberToken): void
    {
        $affected = User::where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->update([
                'password' => $passwordHash,
                'remember_token' => $rememberToken,
            ]);

        if ($affected === 0) {
            throw UserNotFoundException::withIdAndTenant($userId, $tenantId);
        }
    }

    private function toEntity(User $model): UserEntity
    {
        $rawTenantId = $model->getAttribute('tenant_id');
        $tenantId = $rawTenantId !== null ? (string) $rawTenantId : null;
        $rawRole = $model->getAttribute('role');
        $role = $rawRole instanceof \BackedEnum
            ? (string) $rawRole->value
            : (string) ($rawRole ?? 'user');

        // Use fromStateArray factory method instead of private constructor
        return UserEntity::fromStateArray([
            'id' => (string) $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'password' => $model->password,
            'role' => $role,
            'tenant_id' => $tenantId,
            'remember_token' => $model->remember_token,
            'avatar' => $model->avatar,
            'banned_at' => $model->banned_at?->format(\DateTimeImmutable::ATOM),
            'created_at' => $model->created_at?->format(\DateTimeImmutable::ATOM),
            'updated_at' => $model->updated_at?->format(\DateTimeImmutable::ATOM),
        ]);
    }
}
