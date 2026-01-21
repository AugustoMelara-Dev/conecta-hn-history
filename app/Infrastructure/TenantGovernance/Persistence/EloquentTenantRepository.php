<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Persistence;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Models\Tenant;
use DateTimeImmutable;

final readonly class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findById(int $id): ?TenantEntity
    {
        $model = Tenant::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function findBySlug(string $slug): ?TenantEntity
    {
        $model = Tenant::where('slug', $slug)->first();
        return $model ? $this->toEntity($model) : null;
    }

    public function existsBySlug(string $slug): bool
    {
        return Tenant::where('slug', $slug)->exists();
    }

    public function findByDomain(string $domain): ?TenantEntity
    {
        // Assuming 'domain' column or relationship exists. Minimal impl for now.
        $model = Tenant::where('domain', $domain)->first();
        return $model ? $this->toEntity($model) : null;
    }

    public function create(array $attributes): TenantEntity
    {
        $model = Tenant::create($attributes);
        return $this->toEntity($model);
    }

    public function save(TenantEntity $entity): TenantEntity
    {
        // PATRÓN DATA MAPPER: Mapeo manual explícito
        // TenantEntity usa UUIDs como strings, no integers
        
        // 1. DETERMINACIÓN INSERT/UPDATE
        // La entidad tiene id() como string UUID
        $model = Tenant::find($entity->id()) ?? new Tenant();
        
        // 2. MAPEO MANUAL EXPLÍCITO
        // Usar los métodos de la entidad, NO propiedades públicas
        $model->id = $entity->id();
        $model->name = $entity->name();
        $model->slug = $entity->domain(); // domain en Entity = slug en DB
        $model->is_active = $entity->isActive();
        
        // 3. VALORES POR DEFECTO (Business Rules)
        // El plan por defecto para nuevos tenants es 'SEED'
        if (!$model->exists) {
            $model->plan = 'SEED';
        }
        
        // 4. PERSISTENCIA ATÓMICA
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(TenantEntity $entity): void
    {
        Tenant::destroy($entity->id());
    }

    private function toEntity(Tenant $model): TenantEntity
    {
        // TenantEntity tiene constructor privado, usar fromStateArray
        return TenantEntity::fromStateArray([
            'id' => (string) $model->id,
            'name' => $model->name,
            'domain' => $model->slug, // DB slug = Entity domain
            'is_active' => (bool) $model->is_active,
            'created_at' => $model->created_at?->format(DateTimeImmutable::ATOM),
            'updated_at' => $model->updated_at?->format(DateTimeImmutable::ATOM),
        ]);
    }
}
