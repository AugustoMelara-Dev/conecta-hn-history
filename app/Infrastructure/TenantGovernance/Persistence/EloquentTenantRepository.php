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
        // Persistence logic mapping Entity -> Model
        // CRITICAL: Ensure 'source' is saved if logic permits (e.g. if creating from entity)
        // Usually save() implies update if ID exists, or create if not.
        
        $model = $entity->id > 0 ? Tenant::find($entity->id) : new Tenant();
        
        if (!$model) {
            // Fallback for weird edge case
            $model = new Tenant();
        }

        $model->user_id = $entity->userId;
        $model->name = $entity->name;
        $model->slug = $entity->slug;
        $model->source = $entity->source; // CRITICAL: Persist Source
        $model->plan_type = $entity->planType;
        $model->subscription_status = $entity->subscriptionStatus;
        $model->is_active = $entity->isActive;

        $model->save();

        return $this->toEntity($model);
    }

    public function delete(TenantEntity $entity): void
    {
        Tenant::destroy($entity->id);
    }

    private function toEntity(Tenant $model): TenantEntity
    {
        return new TenantEntity(
            id: $model->id,
            userId: $model->user_id,
            name: $model->name,
            slug: $model->slug,
            source: $model->source ?? 'unknown', // Handle legacy data
            planType: $model->plan_type ?? 'semilla',
            subscriptionStatus: $model->subscription_status ?? 'active',
            isActive: (bool) $model->is_active,
            createdAt: $model->created_at instanceof \DateTime ? DateTimeImmutable::createFromMutable($model->created_at) : new DateTimeImmutable((string)($model->created_at ?? 'now')),
            updatedAt: $model->updated_at instanceof \DateTime ? DateTimeImmutable::createFromMutable($model->updated_at) : new DateTimeImmutable((string)($model->updated_at ?? 'now')),
        );
    }
}
