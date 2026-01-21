<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Persistence;

use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Contracts\Events\DomainEventBus;
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
    public function __construct(
        private readonly DomainEventBus $eventBus
    ) {}

    /**
     * Persiste una entidad de dominio UserEntity en la base de datos.
     * 
     * PATRÓN DATA MAPPER: Mapeo manual explícito.
     * Cada campo se asigna individualmente - PROHIBIDO usar fill() o create().
     * 
     * @param UserEntity $user Agregado de dominio a persistir
     */
    public function save(UserEntity $user): void
    {
        // 1. DETERMINACIÓN INSERT/UPDATE mediante findOrNew
        $model = User::findOrNew($user->id());
        
        // 2. MAPEO MANUAL EXPLÍCITO (Data Mapper Pattern)
        // Cada propiedad se extrae del agregado y se asigna al modelo Eloquent
        $model->id = $user->id();               // UUID pre-generado
        $model->name = $user->name();           // String plano
        $model->email = $user->email();         // String validado en entidad
        $model->password = $user->password();   // Hash ya cocinado
        $model->tenant_id = $user->tenantId();  // UUID del tenant
        $model->role = $user->role()->value;    // Enum -> string
        
        // Campos opcionales
        if ($user->rememberToken() !== null) {
            $model->remember_token = $user->rememberToken();
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
        
        if ($user && empty($user->avatar_url)) {
            $user->update(['avatar_url' => $avatarUrl]);
            return true;
        }
        
        return false;
    }

    public function delete(UserEntity $user): void
    {
        if ($user->id() === null) {
            return;
        }
        
        User::where('id', $user->id())->delete();
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

        // Use fromStateArray factory method instead of private constructor
        return UserEntity::fromStateArray([
            'id' => (string) $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'password' => $model->password,
            'role' => $model->role instanceof \UnitEnum ? $model->role->value : ($model->role ?? 'user'),
            'tenant_id' => $tenantId,
            'remember_token' => $model->remember_token,
            'created_at' => $model->created_at?->format(\DateTimeImmutable::ATOM),
            'updated_at' => $model->updated_at?->format(\DateTimeImmutable::ATOM),
        ]);
    }
}
